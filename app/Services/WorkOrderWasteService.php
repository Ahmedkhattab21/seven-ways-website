<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderWasteRecorded;
use App\Models\Employee;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\WorkOrder;
use App\Models\WorkOrderWasteRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderWasteService
{
    public function __construct(private TenantContext $tenant, private WorkOrderCostService $costs)
    {
    }

    public function record(WorkOrder $order, array $data): WorkOrderWasteRecord
    {
        return DB::transaction(function () use ($order, $data) {
            if ($order->company_id !== $this->tenant->companyId()
                || ! $this->tenant->accessibleBranches()->contains('id', $order->branch_id)
                || in_array($order->status, ['awaiting_quality', 'ready_for_delivery', 'delivered', 'cancelled', 'closed'], true)) {
                throw new BusinessRuleException('Waste cannot be recorded for this work order.', status: 403);
            }
            if (! empty($data['work_order_service_id'])
                && ! $order->services()->whereKey($data['work_order_service_id'])->exists()) {
                throw new BusinessRuleException('Waste service line is outside this work order.', status: 403);
            }
            if (! empty($data['product_id'])
                && ! Product::whereKey($data['product_id'])->where('company_id', $order->company_id)->exists()) {
                throw new BusinessRuleException('Waste product is outside this company.', status: 403);
            }
            if (! empty($data['roll_id'])
                && ! InventoryRoll::whereKey($data['roll_id'])->where('company_id', $order->company_id)
                    ->where('warehouse_id', $order->warehouse_id)->exists()) {
                throw new BusinessRuleException('Waste roll is outside this work-order warehouse.', status: 403);
            }
            if (! empty($data['scrap_id'])
                && ! RollScrap::whereKey($data['scrap_id'])->where('company_id', $order->company_id)
                    ->where('warehouse_id', $order->warehouse_id)->exists()) {
                throw new BusinessRuleException('Waste scrap is outside this work-order warehouse.', status: 403);
            }
            if (! empty($data['responsible_employee_id'])
                && ! Employee::whereKey($data['responsible_employee_id'])->where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)->exists()) {
                throw new BusinessRuleException('Responsible employee is outside this work-order branch.', status: 403);
            }
            $basis = $data['area'] ?? $data['quantity'] ?? 0;
            if (bccomp((string) $basis, '0', 6) <= 0) {
                throw new BusinessRuleException('Waste quantity or area must be positive.');
            }
            if (($data['requires_approval'] ?? false) && ! $this->tenant->user()->hasPermission('work_order_materials.approve_excess_waste')) {
                throw new BusinessRuleException('Excess waste requires approval.', status: 403);
            }
            $duplicate = $order->wastes()
                ->where('work_order_service_id', $data['work_order_service_id'] ?? null)
                ->where('product_id', $data['product_id'] ?? null)
                ->where('roll_id', $data['roll_id'] ?? null)
                ->where('scrap_id', $data['scrap_id'] ?? null)
                ->where('quantity', $data['quantity'] ?? null)
                ->where('area', $data['area'] ?? null)
                ->where('reason_code', $data['reason_code'])
                ->lockForUpdate()->exists();
            if ($duplicate) {
                throw new BusinessRuleException('An identical waste record already exists.');
            }
            $record = WorkOrderWasteRecord::query()->create($data + [
                'uuid' => (string) Str::uuid(), 'work_order_id' => $order->id,
                'total_cost' => bcmul((string) $basis, (string) ($data['unit_cost'] ?? 0), 4),
                'approved_by' => ($data['requires_approval'] ?? false) ? $this->tenant->user()->id : null,
                'approved_at' => ($data['requires_approval'] ?? false) ? now() : null,
                'created_by' => $this->tenant->user()->id,
            ]);
            $this->costs->rebuild($order);
            DB::afterCommit(fn () => event(new WorkOrderWasteRecorded($order->id, $record->id)));

            return $record;
        });
    }
}
