<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\VehicleInspection;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryInspectionService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function create(WorkOrder $workOrder): VehicleInspection
    {
        return DB::transaction(function () use ($workOrder) {
            $workOrder = WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                (int) $workOrder->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($workOrder->branch),
                403
            );
            if ($workOrder->status !== 'ready_for_delivery') {
                throw new BusinessRuleException('Delivery inspection requires final quality approval.');
            }

            return VehicleInspection::query()->firstOrCreate(
                ['work_order_id' => $workOrder->id, 'inspection_type' => 'delivery'],
                [
                    'uuid' => (string) Str::uuid(), 'company_id' => $workOrder->company_id,
                    'branch_id' => $workOrder->branch_id, 'vehicle_id' => $workOrder->vehicle_id,
                    'status' => 'draft', 'odometer' => $workOrder->vehicle->odometer,
                ]
            );
        });
    }
}
