<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ReworkCreated;
use App\Models\QualityCheck;
use App\Models\ReworkOrder;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReworkOrderService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function createForFailedCheck(QualityCheck $check, array $data = []): ReworkOrder
    {
        return DB::transaction(function () use ($check, $data) {
            $check = QualityCheck::query()->whereKey($check->id)->lockForUpdate()->with(['items', 'workOrder.services'])->firstOrFail();
            if ($check->status !== 'failed') {
                throw new BusinessRuleException('Rework requires a failed quality check.');
            }
            $existing = ReworkOrder::query()->where('quality_check_id', $check->id)->whereNotIn('status', ['cancelled'])->first();
            if ($existing) {
                return $existing;
            }
            $order = WorkOrder::query()->whereKey($check->work_order_id)->lockForUpdate()->firstOrFail();
            $serviceIds = $check->items->where('result', 'failed')->pluck('work_order_service_id')->filter()->unique();
            if ($serviceIds->isEmpty()) {
                $serviceIds = $order->services()->where('status', '!=', 'cancelled')->pluck('id');
            }
            $rework = new ReworkOrder;
            $rework->fill(collect($data)->only([
                'reason_code', 'reason', 'responsible_employee_id', 'defective_product_id',
                'defective_roll_id', 'defective_batch_number', 'is_customer_chargeable', 'customer_charge_amount',
            ])->all());
            $rework->forceFill([
                'uuid' => (string) Str::uuid(),
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'work_order_id' => $order->id,
                'quality_check_id' => $check->id,
                'rework_number' => $this->numbers->next('rework_order', $order->company_id, $order->branch_id),
                'status' => 'draft',
                'reason' => $data['reason'] ?? $check->rejection_reason ?? 'Quality check failed.',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($serviceIds as $serviceId) {
                $rework->services()->create([
                    'work_order_service_id' => $serviceId,
                    'reason' => $data['reason'] ?? $check->rejection_reason ?? 'Quality check failed.',
                    'required_action' => $data['required_action'] ?? 'Correct the failed quality items.',
                    'status' => 'pending',
                ]);
            }
            $order->services()->whereIn('id', $serviceIds)->update(['status' => 'rework_required', 'completed_at' => null]);
            $from = $order->status;
            $order->forceFill(['status' => 'in_progress', 'finished_at' => null, 'ready_for_quality_at' => null])->save();
            $order->statusLogs()->create([
                'from_status' => $from, 'to_status' => 'in_progress',
                'reason' => "Rework {$rework->rework_number}", 'changed_by' => $this->tenant->user()->id,
            ]);
            $this->audit->record('rework.created', $rework, ['quality_check_id' => $check->id]);
            DB::afterCommit(fn () => event(new ReworkCreated($rework->id, $order->id)));

            return $rework->load('services');
        });
    }
}
