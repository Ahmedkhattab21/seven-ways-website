<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ClosingRunApproved;
use App\Events\FiscalYearClosed;
use App\Events\FiscalYearClosingStarted;
use App\Models\Account;
use App\Models\AccountingClosingRun;
use App\Models\AccountingSetting;
use App\Models\FiscalYear;
use App\Models\YearEndClosingSetting;
use Illuminate\Support\Facades\DB;

class YearEndClosingService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AccountingClosingValidationService $validation,
        private RevenueExpenseClosingService $closing,
        private NetProfitTransferService $profitTransfer,
        private NextFiscalYearService $nextYears,
        private OpeningCarryForwardService $carryForward,
        private AuditService $audit
    ) {
    }

    public function start(FiscalYear $year, string $reason): AccountingClosingRun
    {
        return DB::transaction(function () use ($year, $reason) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            if ($year->company_id !== $this->tenant->companyId() || blank($reason)) {
                throw new BusinessRuleException('A tenant fiscal year and closing reason are required.');
            }
            $existing = AccountingClosingRun::query()->where('company_id', $year->company_id)
                ->where('fiscal_year_id', $year->id)->whereNull('accounting_period_id')
                ->where('closing_type', 'year_end_close')
                ->whereNotIn('status', ['failed', 'cancelled', 'reopened'])->lockForUpdate()->first();
            if ($existing) {
                return $existing->load('checklist.items');
            }
            if ($year->status !== 'soft_closed') {
                throw new BusinessRuleException('Fiscal year must be soft closed before year-end closing starts.');
            }
            $settings = YearEndClosingSetting::query()->where('company_id', $year->company_id)->lockForUpdate()->firstOrFail();
            $this->assertSettings($settings);
            $run = new AccountingClosingRun;
            $run->forceFill([
                'company_id' => $year->company_id, 'fiscal_year_id' => $year->id, 'closing_type' => 'year_end_close',
                'run_number' => $this->numbers->next('accounting_closing_run', $year->company_id),
                'status' => 'validating', 'active_key' => 'active', 'started_by' => $this->tenant->user()->id,
                'started_at' => now(), 'reason' => $reason,
            ])->save();
            $this->audit->record('fiscal_year_closing.started', $run, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new FiscalYearClosingStarted($run->id)));
            $this->validation->validate($run);

            return $run->fresh('checklist.items');
        });
    }

    public function review(AccountingClosingRun $run, string $notes): AccountingClosingRun
    {
        return DB::transaction(function () use ($run, $notes) {
            $run = $this->lockedYearEndRun($run, 'ready_for_review');
            $actor = $this->tenant->user()->id;
            if ($run->started_by === $actor) {
                throw new BusinessRuleException('Year-end starter cannot review the same run.');
            }
            $run->forceFill([
                'status' => 'under_review', 'reviewed_by' => $actor,
                'reviewed_at' => now(), 'review_notes' => $notes,
            ])->save();
            $this->audit->record('fiscal_year_closing.reviewed', $run);

            return $run;
        });
    }

    public function approve(AccountingClosingRun $run, string $notes): AccountingClosingRun
    {
        return DB::transaction(function () use ($run, $notes) {
            $run = $this->lockedYearEndRun($run, 'under_review');
            $actor = $this->tenant->user()->id;
            $settings = AccountingSetting::query()->where('company_id', $run->company_id)->first();
            if ($settings?->separation_of_duties && in_array($actor, [$run->started_by, $run->reviewed_by], true)) {
                throw new BusinessRuleException('Separation of duties requires a different year-end approver.');
            }
            $run->forceFill([
                'status' => 'approved', 'approved_by' => $actor,
                'approved_at' => now(), 'approval_notes' => $notes,
            ])->save();
            $this->audit->record('fiscal_year_closing.approved', $run);
            DB::afterCommit(fn () => event(new ClosingRunApproved($run->id)));

            return $run;
        });
    }

    public function execute(AccountingClosingRun $run): AccountingClosingRun
    {
        return DB::transaction(function () use ($run) {
            $run = $this->lockedYearEndRun($run, 'approved');
            $actor = $this->tenant->user()->id;
            $accountingSettings = AccountingSetting::query()->where('company_id', $run->company_id)->first();
            if ($accountingSettings?->separation_of_duties && $run->approved_by === $actor) {
                throw new BusinessRuleException('Year-end executor must differ from the approver.');
            }
            $year = FiscalYear::query()->whereKey($run->fiscal_year_id)->lockForUpdate()->firstOrFail();
            if ($year->status !== 'soft_closed') {
                throw new BusinessRuleException('Fiscal year status changed during year-end closing.');
            }
            $settings = YearEndClosingSetting::query()->where('company_id', $year->company_id)->lockForUpdate()->firstOrFail();
            $this->assertSettings($settings);
            $period = $year->periods()->orderByDesc('is_adjustment_period')->orderByDesc('end_date')->lockForUpdate()->firstOrFail();
            $run->forceFill(['status' => 'processing'])->save();
            $target = $settings->close_revenue_directly_to_retained_earnings
                ? $settings->retained_earnings_account_id : $settings->income_summary_account_id;
            $this->closing->revenue($run->load('fiscalYear'), $period, $target);
            $this->closing->expense($run->load('fiscalYear'), $period, $target);
            if (! $settings->close_revenue_directly_to_retained_earnings) {
                $this->profitTransfer->transfer(
                    $run->load('fiscalYear'),
                    $period,
                    $settings->income_summary_account_id,
                    $settings->retained_earnings_account_id
                );
            }
            $next = null;
            if ($settings->auto_create_next_fiscal_year || $settings->create_opening_journal) {
                $next = $this->nextYears->getOrCreate($year, $settings->auto_generate_next_periods);
            }
            if ($settings->create_opening_journal && $next) {
                $this->carryForward->create($run, $next);
            }
            $year->forceFill([
                'status' => $settings->lock_year_after_close ? 'locked' : 'closed',
                'is_current' => false, 'closing_run_id' => $run->id,
                'closed_by' => $actor, 'closed_at' => now(),
            ])->save();
            if ($next) {
                FiscalYear::query()->where('company_id', $year->company_id)->whereKeyNot($next->id)->update(['is_current' => false]);
                $next->forceFill(['status' => 'open', 'is_current' => true])->save();
            }
            $run->forceFill([
                'status' => 'completed', 'completed_by' => $actor,
                'completed_at' => now(), 'active_key' => null,
            ])->save();
            $this->audit->record('fiscal_year.closed', $year, ['closing_run_id' => $run->id]);
            DB::afterCommit(fn () => event(new FiscalYearClosed($year->id)));

            return $run->fresh();
        });
    }

    private function lockedYearEndRun(AccountingClosingRun $run, string $status): AccountingClosingRun
    {
        $run = AccountingClosingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
        if ($run->company_id !== $this->tenant->companyId()
            || $run->closing_type !== 'year_end_close'
            || $run->status !== $status) {
            throw new BusinessRuleException('Year-end closing run is not ready for this action.');
        }

        return $run;
    }

    private function assertSettings(YearEndClosingSetting $settings): void
    {
        $ids = array_filter([
            $settings->retained_earnings_account_id,
            $settings->close_revenue_directly_to_retained_earnings ? null : $settings->income_summary_account_id,
        ]);
        if (count($ids) < ($settings->close_revenue_directly_to_retained_earnings ? 1 : 2)
            || Account::query()->where('company_id', $settings->company_id)->where('is_active', true)
                ->where('is_posting', true)->whereIn('id', $ids)->count() !== count($ids)) {
            throw new BusinessRuleException('Valid retained earnings and income summary posting accounts are required.');
        }
    }
}
