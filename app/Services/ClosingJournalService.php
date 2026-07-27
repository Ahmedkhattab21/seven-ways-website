<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\AccountingPostingLink;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;

class ClosingJournalService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(
        AccountingClosingRun $run,
        AccountingPeriod $period,
        string $subtype,
        string $date,
        array $lines,
        bool $opening = false
    ): JournalEntry {
        $action = 'closing:'.$subtype;
        $existing = AccountingPostingLink::query()->where('company_id', $run->company_id)
            ->where('source_type', 'year_closing_run')->where('source_id', $run->id)
            ->where('posting_action', $action)->first();
        if ($existing?->journalEntry) {
            return $existing->journalEntry;
        }
        $lines = array_values(array_filter($lines, fn ($line) => bccomp((string) ($line['debit'] ?? 0), '0', 4) !== 0
            || bccomp((string) ($line['credit'] ?? 0), '0', 4) !== 0));
        $debit = collect($lines)->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['debit'] ?? 0), 4), '0.0000');
        $credit = collect($lines)->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['credit'] ?? 0), 4), '0.0000');
        if ($lines === [] || bccomp($debit, $credit, 4) !== 0) {
            throw new BusinessRuleException('Closing journal must contain balanced non-zero lines.');
        }
        $settings = AccountingSetting::query()->where('company_id', $run->company_id)->firstOrFail();
        $entry = new JournalEntry;
        $entry->forceFill([
            'company_id' => $run->company_id, 'fiscal_year_id' => $period->fiscal_year_id,
            'accounting_period_id' => $period->id,
            'journal_number' => $this->numbers->next('journal_entry', $run->company_id, null, $date),
            'entry_type' => $opening ? 'opening_balance' : 'closing', 'closing_subtype' => $subtype,
            'closing_run_id' => $run->id, 'source_type' => 'year_closing_run', 'source_id' => $run->id,
            'source_number' => $run->run_number, 'status' => 'posted', 'entry_date' => $date,
            'posting_date' => $date, 'currency_id' => $settings->base_currency_id, 'exchange_rate' => 1,
            'description' => str_replace('_', ' ', $subtype).' '.$run->run_number,
            'total_debit' => $debit, 'total_credit' => $credit,
            'base_total_debit' => $debit, 'base_total_credit' => $credit,
            'is_automatic' => true, 'is_opening' => $opening,
            'created_by' => $this->tenant->user()->id, 'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
        ])->save();
        foreach ($lines as $index => $line) {
            $entry->lines()->create([
                'line_number' => $index + 1, 'account_id' => $line['account_id'],
                'branch_id' => $line['branch_id'] ?? null, 'cost_center_id' => $line['cost_center_id'] ?? null,
                'currency_id' => $settings->base_currency_id, 'exchange_rate' => 1,
                'debit_amount' => $line['debit'] ?? 0, 'credit_amount' => $line['credit'] ?? 0,
                'base_debit_amount' => $line['debit'] ?? 0, 'base_credit_amount' => $line['credit'] ?? 0,
                'description' => $line['description'] ?? null,
            ]);
        }
        AccountingPostingLink::query()->create([
            'company_id' => $run->company_id, 'source_type' => 'year_closing_run', 'source_id' => $run->id,
            'source_uuid' => $run->uuid, 'posting_action' => $action, 'journal_entry_id' => $entry->id,
            'idempotency_key' => hash('sha256', $run->company_id.'|'.$run->id.'|'.$action),
            'status' => 'posted', 'created_by' => $this->tenant->user()->id,
        ]);
        $this->audit->record('closing_journal.posted', $entry, ['closing_run_id' => $run->id, 'subtype' => $subtype]);

        return $entry->load('lines');
    }
}
