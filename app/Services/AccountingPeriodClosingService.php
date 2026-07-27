<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountingPeriodClosed;
use App\Events\AccountingPeriodLocked;
use App\Events\AccountingPeriodSoftClosed;
use App\Events\ClosingRunApproved;
use App\Events\ClosingRunStarted;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\AccountingSetting;
use Illuminate\Support\Facades\DB;

class AccountingPeriodClosingService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AccountingClosingValidationService $validation,
        private AuditService $audit
    ) {
    }

    public function start(AccountingPeriod $period, string $type, string $reason): AccountingClosingRun
    {
        $expected = ['period_soft_close' => 'open', 'period_hard_close' => 'soft_closed'];
        if (! isset($expected[$type]) || blank($reason)) {
            throw new BusinessRuleException('Closing run type or reason is invalid.');
        }

        return DB::transaction(function () use ($period, $type, $reason, $expected) {
            $period = AccountingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->company_id !== $this->tenant->companyId() || $period->status !== $expected[$type]) {
                throw new BusinessRuleException('Accounting period is not eligible for this closing run.');
            }
            $existing = AccountingClosingRun::query()->where('company_id', $period->company_id)
                ->where('accounting_period_id', $period->id)->where('closing_type', $type)
                ->whereNotIn('status', ['failed', 'cancelled', 'reopened'])->lockForUpdate()->first();
            if ($existing) {
                return $existing->load('checklist.items');
            }
            $run = new AccountingClosingRun;
            $run->forceFill([
                'company_id' => $period->company_id, 'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id, 'closing_type' => $type,
                'run_number' => $this->numbers->next('accounting_closing_run', $period->company_id, null),
                'status' => 'validating', 'active_key' => 'active', 'started_by' => $this->tenant->user()->id,
                'started_at' => now(), 'reason' => $reason,
            ])->save();
            $this->audit->record('closing_run.started', $run, ['type' => $type, 'reason' => $reason]);
            DB::afterCommit(fn () => event(new ClosingRunStarted($run->id)));
            $this->validation->validate($run);

            return $run->fresh()->load('checklist.items');
        });
    }

    public function review(AccountingClosingRun $run, string $notes): AccountingClosingRun
    {
        return $this->transitionActor($run, 'ready_for_review', 'under_review', 'reviewed_by', 'reviewed_at', $notes, 'review_notes');
    }

    public function approve(AccountingClosingRun $run, string $notes): AccountingClosingRun
    {
        return DB::transaction(function () use ($run, $notes) {
            $run = AccountingClosingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->company_id !== $this->tenant->companyId() || $run->status !== 'under_review') {
                throw new BusinessRuleException('Closing run is not ready for approval.');
            }
            $settings = AccountingSetting::query()->where('company_id', $run->company_id)->first();
            $actor = $this->tenant->user()->id;
            if ($settings?->separation_of_duties && in_array($actor, [$run->started_by, $run->reviewed_by], true)) {
                throw new BusinessRuleException('Separation of duties requires a different approver.');
            }
            if ($run->checklist()->where('status', '!=', 'completed')->exists()
                || $run->checklist?->items()->where('severity', 'blocking')->where('is_required', true)
                    ->whereNotIn('status', ['passed', 'waived', 'not_applicable'])->exists()) {
                throw new BusinessRuleException('Blocking closing checks remain.');
            }
            $period = AccountingPeriod::query()->whereKey($run->accounting_period_id)->lockForUpdate()->firstOrFail();
            $to = $run->closing_type === 'period_soft_close' ? 'soft_closed' : 'closed';
            $expected = $run->closing_type === 'period_soft_close' ? 'open' : 'soft_closed';
            if ($period->status !== $expected) {
                throw new BusinessRuleException('Period status changed during closing.');
            }
            $period->forceFill([
                'status' => $to, 'closed_by' => $actor, 'closed_at' => now(), 'close_reason' => $run->reason,
            ])->save();
            $run->forceFill([
                'status' => 'completed', 'approved_by' => $actor, 'approved_at' => now(),
                'completed_by' => $actor, 'completed_at' => now(), 'approval_notes' => $notes, 'active_key' => null,
            ])->save();
            $this->audit->record('closing_run.approved', $run, ['period_status' => $to]);
            DB::afterCommit(function () use ($run, $period, $to) {
                event(new ClosingRunApproved($run->id));
                event($to === 'soft_closed' ? new AccountingPeriodSoftClosed($period->id) : new AccountingPeriodClosed($period->id));
            });

            return $run;
        });
    }

    public function lock(AccountingPeriod $period, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reason) {
            $period = AccountingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->company_id !== $this->tenant->companyId() || $period->status !== 'closed' || blank($reason)) {
                throw new BusinessRuleException('Only a closed tenant period can be locked with a reason.');
            }
            $period->forceFill(['status' => 'locked', 'close_reason' => $reason])->save();
            $this->audit->record('accounting_period.locked', $period, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new AccountingPeriodLocked($period->id)));

            return $period;
        });
    }

    private function transitionActor(
        AccountingClosingRun $run,
        string $from,
        string $to,
        string $actorField,
        string $timeField,
        string $notes,
        string $notesField
    ): AccountingClosingRun {
        return DB::transaction(function () use ($run, $from, $to, $actorField, $timeField, $notes, $notesField) {
            $run = AccountingClosingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $actor = $this->tenant->user()->id;
            if ($run->company_id !== $this->tenant->companyId() || $run->status !== $from || $run->started_by === $actor) {
                throw new BusinessRuleException('Separation of duties prevents this closing transition.');
            }
            $run->forceFill([$actorField => $actor, $timeField => now(), $notesField => $notes, 'status' => $to])->save();
            $this->audit->record('closing_run.reviewed', $run);

            return $run;
        });
    }
}
