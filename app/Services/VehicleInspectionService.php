<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\InspectionCompleted;
use App\Models\VehicleInspection;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class VehicleInspectionService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function save(VehicleInspection $inspection, array $data, array $items): VehicleInspection
    {
        if ($inspection->status !== 'draft') {
            throw new BusinessRuleException('A completed inspection cannot be edited.');
        }

        return DB::transaction(function () use ($inspection, $data, $items) {
            $inspection->fill($data)->save();
            foreach ($items as $item) {
                $inspection->items()->updateOrCreate(['item_code' => $item['item_code']], $item);
            }

            return $inspection->load('items');
        });
    }

    public function complete(VehicleInspection $inspection, ?string $customerName = null): VehicleInspection
    {
        if ($inspection->status !== 'draft' || ! $inspection->items()->exists()) {
            throw new BusinessRuleException('Inspection must contain at least one item before completion.');
        }

        return DB::transaction(function () use ($inspection, $customerName) {
            if ($inspection->inspection_type === 'delivery') {
                $order = WorkOrder::query()->whereKey($inspection->work_order_id)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'ready_for_delivery'
                    || ! $inspection->attachments()->where('category', 'delivery_overview')->exists()) {
                    throw new BusinessRuleException('Delivery inspection requires final quality approval and final photos.');
                }
            }
            $inspection->forceFill([
                'status' => $customerName ? 'customer_acknowledged' : 'completed',
                'inspected_by' => $this->tenant->user()->id,
                'approved_by_customer_name' => $customerName,
                'customer_approved_at' => $customerName ? now() : null,
                'completed_at' => now(),
            ])->save();
            $order = WorkOrder::query()->whereKey($inspection->work_order_id)->lockForUpdate()->firstOrFail();
            if ($inspection->inspection_type === 'check_in') {
                $from = $order->status;
                $order->forceFill(['status' => 'inspection_completed', 'updated_by' => $this->tenant->user()->id])->save();
                $order->statusLogs()->create(['from_status' => $from, 'to_status' => 'inspection_completed', 'changed_by' => $this->tenant->user()->id]);
            }
            DB::afterCommit(fn () => event(new InspectionCompleted($inspection->id, $order->id)));

            return $inspection;
        });
    }
}
