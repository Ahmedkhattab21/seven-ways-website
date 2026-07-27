<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\CashBoxCount;
use App\Models\CashOverShortAdjustment;
use Illuminate\Support\Facades\DB;

class CashOverShortService
{
    public function __construct(
        private TenantContext $tenant,
        private CashOverShortPostingService $posting,
        private AuditService $audit
    ) {
    }

    public function create(CashBoxCount $count, string $description): CashOverShortAdjustment
    {
        return DB::transaction(function () use ($count, $description) {
            $count = CashBoxCount::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'approved')->whereKey($count->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($count->session->cashBox->branch)) {
                throw new BusinessRuleException('Cash difference branch is outside the actor scope.');
            }
            if (bccomp((string) $count->difference, '0', 4) === 0) {
                throw new BusinessRuleException('Cash over/short requires a non-zero approved difference.');
            }
            $adjustment = CashOverShortAdjustment::query()->where('cash_box_count_id', $count->id)
                ->lockForUpdate()->first() ?? new CashOverShortAdjustment;
            if ($adjustment->exists) {
                return $adjustment;
            }
            $adjustment->forceFill([
                'company_id' => $count->company_id, 'cash_box_session_id' => $count->cash_box_session_id,
                'cash_box_count_id' => $count->id,
                'adjustment_type' => bccomp((string) $count->difference, '0', 4) === 1 ? 'cash_over' : 'cash_short',
                'amount' => ltrim((string) $count->difference, '-'), 'status' => 'draft',
                'description' => $description, 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.cash_over_short.created', $adjustment);

            return $adjustment;
        });
    }

    public function action(CashOverShortAdjustment $adjustment, string $action, ?string $reason = null): CashOverShortAdjustment
    {
        return DB::transaction(function () use ($adjustment, $action, $reason) {
            $adjustment = CashOverShortAdjustment::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($adjustment->session->cashBox->branch)) {
                throw new BusinessRuleException('Cash difference branch is outside the actor scope.');
            }
            if ($action === 'reverse') {
                if ($adjustment->status !== 'posted') {
                    throw new BusinessRuleException('Only a posted cash difference can be reversed.');
                }
                $entry = $this->posting->reverse($adjustment, (string) $reason);
                $adjustment->forceFill([
                    'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $entry->id,
                ])->save();
            } else {
                $transitions = [
                    'submit' => ['draft', 'pending_approval', 'submitted_by'],
                    'approve' => ['pending_approval', 'approved', 'approved_by'],
                    'post' => ['approved', 'posted', 'posted_by'],
                ];
                if (! isset($transitions[$action])) {
                    throw new BusinessRuleException('Unsupported cash over/short action.');
                }
                [$from, $to, $actor] = $transitions[$action];
                if ($adjustment->status !== $from) {
                    throw new BusinessRuleException('Invalid cash over/short transition.');
                }
                $changes = ['status' => $to, $actor => $this->tenant->user()->id];
                if ($action === 'post') {
                    $entry = $this->posting->post($adjustment);
                    $changes['journal_entry_id'] = $entry->id;
                }
                $adjustment->forceFill($changes)->save();
            }
            $this->audit->record('treasury.cash_over_short.'.$action, $adjustment);
            if ($action === 'post') {
                DB::afterCommit(fn () => event(new \App\Events\CashOverShortPosted($adjustment->id)));
            }

            return $adjustment;
        });
    }
}
