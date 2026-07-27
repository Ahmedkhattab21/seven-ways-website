<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingPostingLink;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TreasuryJournalService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingPeriodResolver $periods,
        private DocumentNumberService $numbers,
        private JournalEntryService $journals,
        private AuditService $audit
    ) {
    }

    public function post(
        Model $source,
        string $action,
        string $date,
        ?int $branchId,
        int $currencyId,
        array $lines,
        string $description,
        ?string $reference = null,
        ?string $overrideReason = null
    ): JournalEntry {
        return DB::transaction(function () use (
            $source, $action, $date, $branchId, $currencyId, $lines, $description, $reference, $overrideReason
        ) {
            $source = $source->newQuery()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $source->company_id !== $this->tenant->companyId()) {
                throw new BusinessRuleException('Treasury source is outside the current company.');
            }
            $sourceType = $source::class;
            $link = AccountingPostingLink::query()
                ->where('company_id', $source->company_id)->where('source_type', $sourceType)
                ->where('source_id', $source->getKey())->where('posting_action', $action)
                ->lockForUpdate()->first();
            if ($link?->journal_entry_id) {
                return $link->journalEntry()->with('lines')->firstOrFail();
            }
            $period = $this->periods->resolve(
                $source->company_id, $date, 'treasury', $this->tenant->user(), $overrideReason
            );
            $totalDebit = '0.0000';
            $totalCredit = '0.0000';
            foreach ($lines as $line) {
                $account = Account::query()->where('company_id', $source->company_id)
                    ->where('is_active', true)->where('is_posting', true)->findOrFail($line['account_id']);
                $debit = number_format((float) ($line['debit_amount'] ?? 0), 4, '.', '');
                $credit = number_format((float) ($line['credit_amount'] ?? 0), 4, '.', '');
                if (($debit === '0.0000') === ($credit === '0.0000')) {
                    throw new BusinessRuleException('Each treasury journal line must be debit or credit.');
                }
                $totalDebit = bcadd($totalDebit, $debit, 4);
                $totalCredit = bcadd($totalCredit, $credit, 4);
            }
            if (bccomp($totalDebit, $totalCredit, 4) !== 0 || bccomp($totalDebit, '0', 4) !== 1) {
                throw new BusinessRuleException('Treasury journal must be balanced and positive.');
            }
            $entry = new JournalEntry;
            $entry->forceFill([
                'company_id' => $source->company_id, 'branch_id' => $branchId,
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
                'journal_number' => $this->numbers->next('journal_entry', $source->company_id, $branchId, $date),
                'entry_type' => 'treasury', 'source_type' => $sourceType, 'source_id' => $source->getKey(),
                'source_uuid' => $source->uuid, 'source_number' => $source->document_number ?? null,
                'status' => 'posted', 'entry_date' => $date, 'posting_date' => $date,
                'currency_id' => $currencyId, 'exchange_rate' => 1, 'description' => $description,
                'reference' => $reference, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit,
                'base_total_debit' => $totalDebit, 'base_total_credit' => $totalCredit,
                'is_automatic' => true, 'created_by' => $this->tenant->user()->id,
                'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            foreach (array_values($lines) as $index => $line) {
                $debit = number_format((float) ($line['debit_amount'] ?? 0), 4, '.', '');
                $credit = number_format((float) ($line['credit_amount'] ?? 0), 4, '.', '');
                $entry->lines()->create([
                    'line_number' => $index + 1, 'account_id' => $line['account_id'],
                    'branch_id' => $line['branch_id'] ?? $branchId,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                    'currency_id' => $currencyId, 'exchange_rate' => 1,
                    'debit_amount' => $debit, 'credit_amount' => $credit,
                    'base_debit_amount' => $debit, 'base_credit_amount' => $credit,
                    'description' => $line['description'] ?? $description,
                ]);
            }
            $link ??= new AccountingPostingLink;
            $link->forceFill([
                'company_id' => $source->company_id, 'branch_id' => $branchId,
                'source_type' => $sourceType, 'source_id' => $source->getKey(),
                'source_uuid' => $source->uuid, 'posting_action' => $action,
                'journal_entry_id' => $entry->id,
                'idempotency_key' => hash('sha256', $sourceType.':'.$source->getKey().':'.$action),
                'status' => 'posted', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.journal.posted', $source, [
                'posting_action' => $action, 'journal_entry_id' => $entry->id,
            ]);

            return $entry->load('lines');
        });
    }

    public function reverse(Model $source, string $action, string $reason, ?string $date = null): JournalEntry
    {
        return DB::transaction(function () use ($source, $action, $reason, $date) {
            if (blank($reason)) {
                throw new BusinessRuleException('Reversal reason is required.');
            }
            $link = AccountingPostingLink::query()
                ->where('company_id', $this->tenant->companyId())->where('source_type', $source::class)
                ->where('source_id', $source->getKey())->where('posting_action', $action)
                ->lockForUpdate()->firstOrFail();
            if ($link->status !== 'posted' || $link->reversal_journal_entry_id) {
                throw new BusinessRuleException('Treasury posting is not eligible for reversal.');
            }
            $reversal = $this->journals->reverse($link->journalEntry, $reason, $date);
            $link->forceFill([
                'status' => 'reversed', 'reversal_journal_entry_id' => $reversal->id,
            ])->save();

            return $reversal;
        });
    }
}
