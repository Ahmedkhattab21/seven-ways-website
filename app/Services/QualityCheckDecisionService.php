<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QualityCheckFailed;
use App\Events\QualityCheckPassed;
use App\Events\WorkOrderReadyForDelivery;
use App\Models\QualityCheck;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class QualityCheckDecisionService
{
    public function __construct(
        private TenantContext $tenant,
        private ReworkOrderService $rework,
        private AuditService $audit
    ) {
    }

    public function pass(QualityCheck $qualityCheck, ?string $notes = null): QualityCheck
    {
        return DB::transaction(function () use ($qualityCheck, $notes) {
            $qualityCheck = $this->lockedActive($qualityCheck);
            $order = WorkOrder::query()->whereKey($qualityCheck->work_order_id)->lockForUpdate()->firstOrFail();
            $this->assertCanApprove($order);
            if ($qualityCheck->items()->where('result', 'failed')->exists()
                || $qualityCheck->items()->where('is_required', true)->where('result', 'pending')->exists()
                || $qualityCheck->items()->where('is_critical', true)->whereIn('result', ['failed', 'pending'])->exists()) {
                throw new BusinessRuleException('All required checks must pass before quality approval.');
            }
            $qualityCheck->forceFill([
                'status' => 'passed', 'overall_result' => 'pass', 'requires_rework' => false,
                'general_notes' => $notes, 'completed_at' => now(), 'approved_at' => now(),
                'approved_by' => $this->tenant->user()->id,
            ])->save();
            $from = $order->status;
            $order->forceFill(['status' => 'ready_for_delivery', 'updated_by' => $this->tenant->user()->id])->save();
            $order->statusLogs()->create([
                'from_status' => $from, 'to_status' => 'ready_for_delivery',
                'reason' => "Quality round {$qualityCheck->round_number} passed",
                'changed_by' => $this->tenant->user()->id,
            ]);
            $this->audit->record('quality.check_passed', $qualityCheck, ['work_order_id' => $order->id]);
            DB::afterCommit(function () use ($qualityCheck, $order) {
                event(new QualityCheckPassed($qualityCheck->id, $order->id));
                event(new WorkOrderReadyForDelivery($order->id, $qualityCheck->id));
            });

            return $qualityCheck;
        });
    }

    public function fail(QualityCheck $qualityCheck, string $reason, array $reworkData = []): QualityCheck
    {
        $qualityCheck = DB::transaction(function () use ($qualityCheck, $reason) {
            $qualityCheck = $this->lockedActive($qualityCheck);
            $order = WorkOrder::query()->whereKey($qualityCheck->work_order_id)->lockForUpdate()->firstOrFail();
            $this->assertCanApprove($order);
            if (! $qualityCheck->items()->where('result', 'failed')->exists()) {
                throw new BusinessRuleException('At least one failed check item is required.');
            }
            $needsPhoto = $qualityCheck->items()->where('result', 'failed')->where('photo_required', true)->exists();
            if ($needsPhoto && ! $qualityCheck->attachments()->where('category', 'quality_failure')->exists()) {
                throw new BusinessRuleException('A private failure photo is required.');
            }
            $qualityCheck->forceFill([
                'status' => 'failed', 'overall_result' => 'fail', 'requires_rework' => true,
                'rejection_reason' => $reason, 'completed_at' => now(), 'approved_at' => now(),
                'approved_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('quality.check_failed', $qualityCheck, ['work_order_id' => $order->id]);
            DB::afterCommit(fn () => event(new QualityCheckFailed($qualityCheck->id, $order->id)));

            return $qualityCheck;
        });
        $this->rework->createForFailedCheck($qualityCheck, array_merge($reworkData, ['reason' => $reason]));

        return $qualityCheck->refresh();
    }

    private function lockedActive(QualityCheck $qualityCheck): QualityCheck
    {
        $qualityCheck = QualityCheck::query()->whereKey($qualityCheck->id)->lockForUpdate()->firstOrFail();
        if (! in_array($qualityCheck->status, ['draft', 'in_progress'], true)) {
            throw new BusinessRuleException('The quality check is already complete.');
        }

        return $qualityCheck;
    }

    private function assertCanApprove(WorkOrder $order): void
    {
        abort_unless(
            (int) $order->company_id === (int) $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($order->branch),
            403
        );
        if (config('quality.separation_of_duties')
            && $order->services()->whereHas('technicians.employee', fn ($query) => $query->where('user_id', $this->tenant->user()->id))->exists()) {
            throw new BusinessRuleException('A technician cannot approve their own work.', status: 403);
        }
    }
}
