<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\JournalEntryApproved;
use App\Events\JournalEntryCancelled;
use App\Events\JournalEntryCreated;
use App\Events\JournalEntryPosted;
use App\Events\JournalEntryReversed;
use App\Events\JournalEntrySubmitted;
use App\Models\AccountingSetting;
use App\Models\BankAdjustment;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AccountingPeriodResolver $periods,
        private JournalEntryValidationService $validator,
        private PostingAmountResolver $amounts,
        private AuditService $audit
    ) {
    }

    public function createManual(array $data): JournalEntry
    {
        $companyId = $this->tenant->companyId();
        $this->assertBranchScope($data['branch_id'] ?? null);
        $settings = AccountingSetting::query()->where('company_id', $companyId)->firstOrFail();
        if (! $settings->allow_manual_journals) {
            throw new BusinessRuleException('Manual journals are disabled in accounting settings.');
        }
        $period = $this->periods->resolve($companyId, $data['entry_date'], 'manual_journals', $this->tenant->user(), $data['override_reason'] ?? null);

        return DB::transaction(function () use ($data, $companyId, $settings, $period) {
            $entry = new JournalEntry($data);
            $entry->forceFill([
                'company_id' => $companyId, 'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id,
                'journal_number' => $this->numbers->next('journal_entry', $companyId, $data['branch_id'] ?? null, $data['entry_date']),
                'entry_type' => 'manual', 'status' => 'draft',
                'currency_id' => $settings->base_currency_id, 'exchange_rate' => '1.00000000',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->replaceLines($entry, $data['lines']);
            $this->audit->record('journal.created', $entry);
            DB::afterCommit(fn () => event(new JournalEntryCreated($entry->id)));

            return $entry->load('lines');
        });
    }

    public function updateManual(JournalEntry $entry, array $data): JournalEntry
    {
        $this->assertCompanyAndStatus($entry, 'draft');
        $this->assertBranchScope($data['branch_id'] ?? null);

        return DB::transaction(function () use ($entry, $data) {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $period = $this->periods->resolve($entry->company_id, $data['entry_date'], 'manual_journals', $this->tenant->user(), $data['override_reason'] ?? null);
            $entry->forceFill([
                'branch_id' => $data['branch_id'] ?? null, 'entry_date' => $data['entry_date'],
                'description' => $data['description'], 'reference' => $data['reference'] ?? null,
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
            ])->save();
            $entry->lines()->delete();
            $this->replaceLines($entry, $data['lines']);
            $this->audit->record('journal.updated', $entry);

            return $entry->load('lines');
        });
    }

    public function action(JournalEntry $entry, string $action): JournalEntry
    {
        return DB::transaction(function () use ($entry, $action) {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->company_id !== $this->tenant->companyId() || $entry->is_automatic) {
                throw new BusinessRuleException('Journal is outside the allowed manual scope.');
            }
            $settings = AccountingSetting::query()->where('company_id', $entry->company_id)->firstOrFail();
            $transitions = [
                'submit' => ['draft', 'pending_approval', 'submitted_by', 'submitted_at', JournalEntrySubmitted::class],
                'approve' => ['pending_approval', 'approved', 'approved_by', 'approved_at', JournalEntryApproved::class],
                'post' => [$settings->require_journal_approval ? 'approved' : 'pending_approval', 'posted', 'posted_by', 'posted_at', JournalEntryPosted::class],
                'cancel' => [['draft', 'pending_approval', 'approved'], 'cancelled', 'cancelled_by', 'cancelled_at', JournalEntryCancelled::class],
            ];
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported journal action.');
            }
            [$from, $to, $actor, $timestamp, $event] = $transitions[$action];
            if (! in_array($entry->status, (array) $from, true)) {
                throw new BusinessRuleException('Invalid journal status transition.');
            }
            if (in_array($action, ['submit', 'approve', 'post'], true)) {
                $this->validator->assertPostable($entry, $this->tenant->user()->hasPermission('accounting.journals.post_control_accounts'));
            }
            if ($settings->separation_of_duties && $action === 'approve' && $entry->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Separation of duties prevents self-approval.');
            }
            $changes = ['status' => $to, $actor => $this->tenant->user()->id, $timestamp => now()];
            if ($action === 'post') {
                $module = $entry->entry_type === 'adjustment' ? 'adjustments' : 'manual_journals';
                $period = $this->periods->resolve($entry->company_id, $entry->entry_date->toDateString(), $module, $this->tenant->user());
                $changes += ['accounting_period_id' => $period->id, 'fiscal_year_id' => $period->fiscal_year_id, 'posting_date' => $entry->entry_date];
            }
            $entry->forceFill($changes)->save();
            $this->audit->record('journal.'.$action, $entry);
            DB::afterCommit(fn () => event(new $event($entry->id)));

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, string $reason, ?string $date = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $date) {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->company_id !== $this->tenant->companyId() || $entry->status !== 'posted' || $entry->reversed_by_entry_id) {
                throw new BusinessRuleException('Only an unreversed posted journal can be reversed.');
            }
            $date ??= now()->toDateString();
            $module = $entry->source_type === BankAdjustment::class || $entry->entry_type === 'treasury'
                ? 'treasury' : ($entry->entry_type === 'adjustment' ? 'adjustments' : 'journals');
            $period = $this->periods->resolve($entry->company_id, $date, $module, $this->tenant->user());
            $reversal = $entry->replicate([
                'uuid', 'journal_number', 'status', 'source_id', 'source_uuid', 'source_number',
                'reversal_of_id', 'reversed_by_entry_id', 'created_by', 'submitted_by', 'submitted_at',
                'approved_by', 'approved_at', 'posted_by', 'posted_at', 'reversed_by', 'reversed_at',
                'cancelled_by', 'cancelled_at', 'created_at', 'updated_at',
            ]);
            $reversal->forceFill([
                'journal_number' => $this->numbers->next('journal_entry', $entry->company_id, $entry->branch_id, $date),
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
                'entry_type' => 'reversal', 'status' => 'posted', 'entry_date' => $date, 'posting_date' => $date,
                'description' => 'Reversal: '.$entry->description, 'reversal_of_id' => $entry->id,
                'is_automatic' => true, 'is_reversal' => true, 'reversal_reason' => $reason,
                'created_by' => $this->tenant->user()->id, 'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    ...$line->only(['line_number', 'account_id', 'branch_id', 'cost_center_id', 'currency_id', 'customer_id',
                        'supplier_id', 'employee_id', 'vehicle_id', 'product_id', 'warehouse_id', 'tax_id',
                        'exchange_rate', 'tax_component', 'description', 'reference', 'metadata']),
                    'debit_amount' => $line->credit_amount, 'credit_amount' => $line->debit_amount,
                    'base_debit_amount' => $line->base_credit_amount, 'base_credit_amount' => $line->base_debit_amount,
                ]);
            }
            $entry->forceFill([
                'status' => 'posted', 'reversed_by_entry_id' => $reversal->id,
                'reversed_by' => $this->tenant->user()->id, 'reversed_at' => now(), 'reversal_reason' => $reason,
            ])->save();
            $this->audit->record('journal.reversed', $entry, ['reversal_journal_entry_id' => $reversal->id, 'reason' => $reason]);
            DB::afterCommit(fn () => event(new JournalEntryReversed($entry->id)));

            return $reversal->load('lines');
        });
    }

    private function replaceLines(JournalEntry $entry, array $lines): void
    {
        $totalDebit = '0.0000';
        $totalCredit = '0.0000';
        foreach (array_values($lines) as $index => $data) {
            $this->assertLineScope($data, $entry->company_id);
            $account = \App\Models\Account::query()->findOrFail($data['account_id']);
            $this->validator->assertAccount(
                $account, $data, $entry->company_id,
                $this->tenant->user()->hasPermission('accounting.journals.post_control_accounts')
            );
            $debit = $this->amounts->amount($data, 'debit_amount');
            $credit = $this->amounts->amount($data, 'credit_amount');
            $rate = (string) ($data['exchange_rate'] ?? '1');
            $entry->lines()->create($data + [
                'line_number' => $index + 1, 'currency_id' => $data['currency_id'] ?? $entry->currency_id,
                'exchange_rate' => $rate, 'debit_amount' => $debit, 'credit_amount' => $credit,
                'base_debit_amount' => $this->amounts->base($debit, $rate),
                'base_credit_amount' => $this->amounts->base($credit, $rate),
            ]);
            $totalDebit = bcadd($totalDebit, $this->amounts->base($debit, $rate), 4);
            $totalCredit = bcadd($totalCredit, $this->amounts->base($credit, $rate), 4);
        }
        $entry->forceFill([
            'total_debit' => $totalDebit, 'total_credit' => $totalCredit,
            'base_total_debit' => $totalDebit, 'base_total_credit' => $totalCredit,
        ])->save();
    }

    private function assertCompanyAndStatus(JournalEntry $entry, string $status): void
    {
        if ($entry->company_id !== $this->tenant->companyId() || $entry->status !== $status || $entry->is_automatic) {
            throw new BusinessRuleException('Journal is not editable.');
        }
    }

    private function assertBranchScope(?int $branchId): void
    {
        if (! $branchId) {
            return;
        }
        $branch = Branch::query()->where('company_id', $this->tenant->companyId())->findOrFail($branchId);
        if (! $this->tenant->user()->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch is outside the accessible scope.');
        }
    }

    private function assertLineScope(array $line, int $companyId): void
    {
        foreach ([
            'branch_id' => Branch::class,
            'cost_center_id' => CostCenter::class,
            'customer_id' => Customer::class,
            'supplier_id' => Supplier::class,
        ] as $field => $model) {
            if (! empty($line[$field])
                && ! $model::query()->where('company_id', $companyId)->whereKey($line[$field])->exists()) {
                throw new BusinessRuleException("Journal line {$field} is outside the current company.");
            }
        }
    }
}
