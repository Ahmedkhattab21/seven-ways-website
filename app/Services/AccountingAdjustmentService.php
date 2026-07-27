<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AdjustmentEntryCreated;
use App\Events\AdjustmentEntryPosted;
use App\Models\Account;
use App\Models\AccountingAdjustment;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class AccountingAdjustmentService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingPeriodResolver $periods,
        private DocumentNumberService $numbers,
        private JournalEntryService $journals,
        private AuditService $audit
    ) {
    }

    public function create(array $data): AccountingAdjustment
    {
        $companyId = $this->tenant->companyId();
        $period = $this->periods->resolve($companyId, $data['entry_date'], 'adjustments', $this->tenant->user());
        if (blank($data['description']) || blank($data['adjustment_type'])) {
            throw new BusinessRuleException('Adjustment description and type are required.');
        }

        return DB::transaction(function () use ($data, $companyId, $period) {
            $settings = AccountingSetting::query()->where('company_id', $companyId)->firstOrFail();
            $entry = new JournalEntry;
            $entry->forceFill([
                'company_id' => $companyId, 'branch_id' => $data['branch_id'] ?? null,
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
                'journal_number' => $this->numbers->next('journal_entry', $companyId, $data['branch_id'] ?? null, $data['entry_date']),
                'entry_type' => 'adjustment', 'status' => 'draft', 'entry_date' => $data['entry_date'],
                'currency_id' => $settings->base_currency_id, 'exchange_rate' => 1,
                'description' => $data['description'], 'reference' => $data['supporting_reference'] ?? null,
                'is_automatic' => false, 'is_adjusting' => true, 'created_by' => $this->tenant->user()->id,
            ])->save();
            $debit = $credit = '0.0000';
            foreach (array_values($data['lines']) as $index => $line) {
                $account = Account::query()->where('company_id', $companyId)->where('is_posting', true)->findOrFail($line['account_id']);
                if (! $account->is_active || (! $account->allow_manual_entry && ! $this->tenant->user()->hasPermission('accounting.journals.post_control_accounts'))) {
                    throw new BusinessRuleException('Adjustment account is not available for manual posting.');
                }
                $lineDebit = bcadd((string) ($line['debit_amount'] ?? 0), '0', 4);
                $lineCredit = bcadd((string) ($line['credit_amount'] ?? 0), '0', 4);
                if ((bccomp($lineDebit, '0', 4) === 1) === (bccomp($lineCredit, '0', 4) === 1)) {
                    throw new BusinessRuleException('Each adjustment line must have exactly one side.');
                }
                $entry->lines()->create([
                    'line_number' => $index + 1, 'account_id' => $account->id,
                    'branch_id' => $line['branch_id'] ?? $data['branch_id'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'currency_id' => $settings->base_currency_id, 'exchange_rate' => 1,
                    'debit_amount' => $lineDebit, 'credit_amount' => $lineCredit,
                    'base_debit_amount' => $lineDebit, 'base_credit_amount' => $lineCredit,
                    'description' => $line['description'] ?? null,
                ]);
                $debit = bcadd($debit, $lineDebit, 4);
                $credit = bcadd($credit, $lineCredit, 4);
            }
            if (bccomp($debit, $credit, 4) !== 0 || bccomp($debit, '0', 4) !== 1) {
                throw new BusinessRuleException('Adjustment journal must be balanced and non-zero.');
            }
            $entry->forceFill([
                'total_debit' => $debit, 'total_credit' => $credit,
                'base_total_debit' => $debit, 'base_total_credit' => $credit,
            ])->save();
            $adjustment = AccountingAdjustment::query()->create([
                'company_id' => $companyId, 'journal_entry_id' => $entry->id,
                'adjustment_type' => $data['adjustment_type'],
                'supporting_reference' => $data['supporting_reference'] ?? null,
                'reversal_policy' => $data['reversal_policy'] ?? 'none',
                'scheduled_reversal_date' => $data['scheduled_reversal_date'] ?? null,
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ]);
            $this->audit->record('accounting_adjustment.created', $adjustment);
            DB::afterCommit(fn () => event(new AdjustmentEntryCreated($adjustment->id)));

            return $adjustment->load('journalEntry.lines');
        });
    }

    public function action(AccountingAdjustment $adjustment, string $action): AccountingAdjustment
    {
        if ($adjustment->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Adjustment is outside the current company.');
        }
        $entry = $this->journals->action($adjustment->journalEntry, $action);
        $adjustment->forceFill(['status' => $entry->status])->save();
        if ($entry->status === 'posted') {
            DB::afterCommit(fn () => event(new AdjustmentEntryPosted($adjustment->id)));
        }

        return $adjustment->fresh('journalEntry.lines');
    }
}
