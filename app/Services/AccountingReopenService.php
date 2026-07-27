<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountingPeriodReopened;
use App\Events\FiscalYearReopened;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class AccountingReopenService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private JournalEntryService $journals,
        private AuditService $audit
    ) {
    }

    public function period(AccountingPeriod $period, string $reason, bool $exceptional = false): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reason, $exceptional) {
            $period = AccountingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->company_id !== $this->tenant->companyId() || blank($reason)
                || $period->status === 'open' || ($period->status === 'locked' && ! $exceptional)) {
                throw new BusinessRuleException('Period reopen is not permitted.');
            }
            $period->forceFill([
                'status' => $period->status === 'closed' ? 'soft_closed' : 'open',
                'reopened_by' => $this->tenant->user()->id, 'reopened_at' => now(), 'close_reason' => $reason,
            ])->save();
            $this->audit->record('accounting_period.reopened', $period, compact('reason', 'exceptional'));
            DB::afterCommit(fn () => event(new AccountingPeriodReopened($period->id)));

            return $period;
        });
    }

    public function startFiscalYear(FiscalYear $year, string $reason): AccountingClosingRun
    {
        return DB::transaction(function () use ($year, $reason) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            if ($year->company_id !== $this->tenant->companyId()
                || ! in_array($year->status, ['closed', 'locked'], true)
                || blank($reason)) {
                throw new BusinessRuleException('Only a closed tenant fiscal year can start controlled reopen.');
            }
            $existing = AccountingClosingRun::query()->where('company_id', $year->company_id)
                ->where('fiscal_year_id', $year->id)->where('closing_type', 'reopen_year')
                ->whereNotIn('status', ['failed', 'cancelled'])->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $run = new AccountingClosingRun;
            $run->forceFill([
                'company_id' => $year->company_id, 'fiscal_year_id' => $year->id,
                'closing_type' => 'reopen_year',
                'run_number' => $this->numbers->next('accounting_closing_run', $year->company_id),
                'status' => 'under_review', 'active_key' => 'active',
                'started_by' => $this->tenant->user()->id, 'started_at' => now(),
                'reason' => $reason,
            ])->save();
            $this->audit->record('fiscal_year_reopen.started', $run, ['reason' => $reason]);

            return $run;
        });
    }

    public function approveFiscalYear(AccountingClosingRun $run, string $notes = ''): FiscalYear
    {
        return DB::transaction(function () use ($run, $notes) {
            $run = AccountingClosingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $actor = $this->tenant->user()->id;
            if ($run->company_id !== $this->tenant->companyId()
                || $run->closing_type !== 'reopen_year'
                || $run->status !== 'under_review'
                || $run->started_by === $actor) {
                throw new BusinessRuleException('Fiscal-year reopen requires an independent approver.');
            }
            $year = FiscalYear::query()->whereKey($run->fiscal_year_id)->lockForUpdate()->firstOrFail();
            if (! in_array($year->status, ['closed', 'locked'], true)) {
                throw new BusinessRuleException('Fiscal year is no longer eligible for controlled reopen.');
            }
            $next = FiscalYear::query()->where('company_id', $year->company_id)
                ->whereDate('start_date', '>', $year->end_date)->orderBy('start_date')->lockForUpdate()->first();
            if ($next && JournalEntry::query()->where('fiscal_year_id', $next->id)->where('status', 'posted')
                ->where(fn ($query) => $query->whereNull('closing_subtype')->orWhere('closing_subtype', '!=', 'opening_carry_forward'))->exists()) {
                throw new BusinessRuleException('Next-year posted activity blocks fiscal-year reopen.');
            }
            if (! $next || ! $year->closing_run_id) {
                throw new BusinessRuleException('Closing journals and the generated next fiscal year are required for reopen.');
            }
            $date = $next->start_date->toDateString();
            $closingEntries = JournalEntry::query()->where('closing_run_id', $year->closing_run_id)
                ->where('status', 'posted')->whereNull('reversed_by_entry_id')
                ->orderByDesc('is_opening')->orderByDesc('id')->lockForUpdate()->get();
            if ($closingEntries->isEmpty()) {
                throw new BusinessRuleException('No unreversed closing journals are available.');
            }
            foreach ($closingEntries as $entry) {
                $this->journals->reverse($entry, $run->reason, $date);
            }
            $year->forceFill([
                'status' => 'soft_closed', 'is_current' => true,
                'reopened_by' => $actor, 'reopened_at' => now(),
            ])->save();
            $next->forceFill(['is_current' => false])->save();
            AccountingClosingRun::query()->whereKey($year->closing_run_id)->update([
                'status' => 'reopened', 'reopened_by' => $actor, 'reopened_at' => now(),
            ]);
            $run->forceFill([
                'status' => 'completed', 'reviewed_by' => $actor, 'reviewed_at' => now(),
                'approved_by' => $actor, 'approved_at' => now(), 'approval_notes' => $notes,
                'completed_by' => $actor, 'completed_at' => now(), 'active_key' => null,
            ])->save();
            $this->audit->record('fiscal_year.reopened', $year, [
                'reason' => $run->reason, 'reopen_run_id' => $run->id,
                'reversal_count' => $closingEntries->count(),
            ]);
            DB::afterCommit(fn () => event(new FiscalYearReopened($year->id)));

            return $year;
        });
    }
}
