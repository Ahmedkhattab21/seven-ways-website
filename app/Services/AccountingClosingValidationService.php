<?php

namespace App\Services;

use App\Events\ClosingValidationCompleted;
use App\Events\ClosingValidationFailed;
use App\Models\AccountingClosingException;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPostingLink;
use App\Models\JournalEntry;
use App\Models\YearEndClosingSetting;
use Illuminate\Support\Facades\DB;

class AccountingClosingValidationService
{
    public function __construct(
        private AccountingClosingChecklistService $checklists,
        private TrialBalanceService $trialBalance,
        private UnpostedAccountingSourcesService $unposted,
        private ControlAccountReconciliationService $reconciliations
    ) {
    }

    public function validate(AccountingClosingRun $run): array
    {
        $run->load(['fiscalYear', 'period']);
        $filters = [
            'date_from' => $run->period?->start_date->toDateString() ?? $run->fiscalYear->start_date->toDateString(),
            'date_to' => $run->period?->end_date->toDateString() ?? $run->fiscalYear->end_date->toDateString(),
        ];
        $trial = $this->trialBalance->report($filters);
        $checks = [
            'TRIAL_BALANCE_BALANCED' => $trial['balanced'],
            'NO_UNPOSTED_JOURNALS' => ! JournalEntry::query()->where('company_id', $run->company_id)
                ->whereBetween('entry_date', [$filters['date_from'], $filters['date_to']])
                ->whereIn('status', ['draft', 'pending_approval', 'approved'])->exists(),
            'NO_UNPOSTED_SOURCES' => $this->unposted->count($filters) === 0,
            'NO_MISSING_COST_CENTERS' => ! DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('accounts as a', 'a.id', '=', 'jel.account_id')
                ->where('je.company_id', $run->company_id)->where('je.status', 'posted')
                ->whereBetween('je.posting_date', [$filters['date_from'], $filters['date_to']])
                ->where('a.requires_cost_center', true)->whereNull('jel.cost_center_id')->exists(),
            'NO_FAILED_POSTINGS' => ! AccountingPostingLink::query()->where('company_id', $run->company_id)
                ->where('status', 'failed')->exists(),
            'NO_UNPOSTED_TREASURY' => ! $this->hasUnpostedTreasury(
                $run->company_id, $filters['date_from'], $filters['date_to']
            ),
        ];
        if (! $run->accounting_period_id) {
            $settings = YearEndClosingSetting::query()->where('company_id', $run->company_id)->first();
            $ar = $this->reconciliations->report('customers', $filters);
            $ap = $this->reconciliations->report('suppliers', $filters);
            $inventory = $this->reconciliations->report('inventory', $filters);
            $vatOutput = $this->reconciliations->report('vat_output', $filters);
            $vatInput = $this->reconciliations->report('vat_input', $filters);
            $checks += [
                'ALL_PERIODS_CLOSED' => ! $run->fiscalYear->periods()->where('is_adjustment_period', false)
                    ->whereNotIn('status', ['closed', 'locked'])->exists(),
                'RETAINED_EARNINGS_CONFIGURED' => (bool) $settings?->retained_earnings_account_id
                    && ($settings->close_revenue_directly_to_retained_earnings || (bool) $settings->income_summary_account_id),
                'NEXT_YEAR_READY' => true,
                'AR_RECONCILED' => $this->within($ar['difference'], $settings?->ar_reconciliation_tolerance ?? 0),
                'AP_RECONCILED' => $this->within($ap['difference'], $settings?->ap_reconciliation_tolerance ?? 0),
                'INVENTORY_RECONCILED' => $this->within($inventory['difference'], $settings?->inventory_reconciliation_tolerance ?? 0),
                'VAT_RECONCILED' => $this->within($vatOutput['difference'], $settings?->vat_reconciliation_tolerance ?? 0)
                    && $this->within($vatInput['difference'], $settings?->vat_reconciliation_tolerance ?? 0),
            ];
        }
        $checklist = $this->checklists->create($run);
        foreach ($checklist->items as $item) {
            $passed = $checks[$item->code] ?? true;
            $item->forceFill([
                'status' => $passed ? 'passed' : 'failed',
                'result_summary' => $passed ? 'Automated check passed.' : 'Automated check failed.',
                'blocking_reason' => $passed ? null : $item->name_en,
                'evidence' => ['check' => $item->code, 'passed' => $passed],
                'checked_by' => $run->started_by, 'checked_at' => now(),
            ])->save();
            if (! $passed) {
                AccountingClosingException::query()->firstOrCreate(
                    ['closing_run_id' => $run->id, 'exception_type' => $item->code, 'status' => 'open'],
                    [
                        'company_id' => $run->company_id, 'severity' => 'blocking',
                        'description' => $item->blocking_reason,
                    ]
                );
            }
        }
        $blocking = collect($checks)->filter(fn ($passed) => ! $passed)->keys()->values();
        $checklist->forceFill([
            'completed_items' => $checklist->items()->whereIn('status', ['passed', 'warning', 'waived', 'not_applicable'])->count(),
            'status' => $blocking->isEmpty() ? 'completed' : 'blocked',
            'completed_by' => $blocking->isEmpty() ? $run->started_by : null,
            'completed_at' => $blocking->isEmpty() ? now() : null,
        ])->save();
        $snapshot = [
            'blocking_errors' => $blocking->all(), 'warnings' => [],
            'passed_checks' => collect($checks)->filter()->keys()->values()->all(),
            'totals' => $trial['totals'], 'affected_sources' => [], 'affected_accounts' => [],
        ];
        $run->forceFill([
            'status' => $blocking->isEmpty() ? 'ready_for_review' : 'validation_failed',
            'validation_snapshot' => $snapshot,
        ])->save();
        DB::afterCommit(fn () => event($blocking->isEmpty()
            ? new ClosingValidationCompleted($run->id)
            : new ClosingValidationFailed($run->id)));

        return $snapshot;
    }

    private function within(string $difference, string|int|float $tolerance): bool
    {
        return bccomp(ltrim($difference, '-'), (string) $tolerance, 4) <= 0;
    }

    private function hasUnpostedTreasury(int $companyId, string $from, string $to): bool
    {
        foreach ([
            ['treasury_transfers', 'transfer_date'],
            ['cash_receipts', 'document_date'],
            ['cash_payments', 'document_date'],
            ['merchant_settlements', 'settlement_date'],
        ] as [$table, $date]) {
            if (DB::table($table)->where('company_id', $companyId)
                ->whereBetween($date, [$from, $to])
                ->whereIn('status', ['pending_approval', 'approved', 'processing', 'failed'])->exists()) {
                return true;
            }
        }

        return DB::table('cheques')->where('company_id', $companyId)
            ->whereBetween('issue_date', [$from, $to])
            ->whereIn('status', ['received', 'issued', 'deposited', 'under_collection', 'presented'])
            ->exists();
    }
}
