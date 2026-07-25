<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ReworkCompleted;
use App\Events\ReworkStarted;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\ReworkOrder;
use App\Models\RollScrap;
use App\Models\Warehouse;
use App\Models\WarrantyClaim;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReworkExecutionService
{
    public function __construct(
        private TenantContext $tenant,
        private ReworkCostService $costs,
        private WorkOrderCostService $workOrderCosts,
        private InventoryReservationService $reservations,
        private AuditService $audit
    ) {
    }

    public function addMaterial(ReworkOrder $rework, array $data): WorkOrderMaterial
    {
        return DB::transaction(function () use ($rework, $data) {
            $rework = $this->lockScoped($rework);
            if (! in_array($rework->status, ['approved', 'in_progress'], true)) {
                throw new BusinessRuleException('Materials can only be added to approved or active rework.');
            }
            $serviceId = (int) $data['work_order_service_id'];
            if (! $rework->services()->where('work_order_service_id', $serviceId)->exists()) {
                throw new BusinessRuleException('The material service is outside this rework order.');
            }
            $product = Product::query()->whereKey($data['product_id'])->where('company_id', $rework->company_id)->firstOrFail();
            $warehouse = Warehouse::query()->whereKey($data['warehouse_id'])
                ->where('company_id', $rework->company_id)->where('branch_id', $rework->branch_id)
                ->where('is_active', true)->where('is_system', false)->where('allows_work_order_issue', true)->firstOrFail();
            $line = new WorkOrderMaterial;
            $line->forceFill([
                'uuid' => (string) Str::uuid(), 'work_order_id' => $rework->work_order_id,
                'work_order_service_id' => $serviceId, 'rework_order_id' => $rework->id,
                'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
                'roll_id' => $data['roll_id'] ?? null, 'scrap_id' => $data['scrap_id'] ?? null,
                'material_type' => $data['material_type'], 'expected_quantity' => $data['expected_quantity'],
                'unit_id' => $product->stock_unit_id, 'status' => 'planned',
                'notes' => $data['notes'] ?? null,
            ])->save();
            $this->audit->record('rework.material_added', $rework, ['material_id' => $line->id]);

            return $line;
        });
    }

    public function reserveMaterial(WorkOrderMaterial $line): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->with('reworkOrder')->firstOrFail();
            if (! $line->rework_order_id || $line->status !== 'planned'
                || ! in_array($line->reworkOrder->status, ['approved', 'in_progress'], true)) {
                throw new BusinessRuleException('A planned rework material is required.');
            }
            $this->lockScoped($line->reworkOrder);
            $reservation = $this->reservations->reserve(
                Warehouse::findOrFail($line->warehouse_id),
                Product::findOrFail($line->product_id),
                $line->expected_quantity,
                'rework_order',
                $line->rework_order_id,
                null,
                $line->roll_id ? InventoryRoll::findOrFail($line->roll_id) : null,
                $line->scrap_id ? RollScrap::findOrFail($line->scrap_id) : null,
            );
            $line->forceFill(['reservation_id' => $reservation->id, 'status' => 'reserved'])->save();

            return $line;
        });
    }

    public function approve(ReworkOrder $rework): ReworkOrder
    {
        return DB::transaction(function () use ($rework) {
            $rework = $this->lockScoped($rework);
            if ($rework->status !== 'draft') {
                throw new BusinessRuleException('Only draft rework orders can be approved.');
            }
            $rework->forceFill(['status' => 'approved', 'approved_by' => $this->tenant->user()->id])->save();
            $this->audit->record('rework.approved', $rework);

            return $rework;
        });
    }

    public function start(ReworkOrder $rework): ReworkOrder
    {
        return DB::transaction(function () use ($rework) {
            $rework = $this->lockScoped($rework);
            if ($rework->status !== 'approved') {
                throw new BusinessRuleException('Only approved rework orders can start.');
            }
            $rework->forceFill(['status' => 'in_progress', 'started_at' => now()])->save();
            $rework->services()->where('status', 'pending')->update(['status' => 'in_progress', 'started_at' => now()]);
            $this->audit->record('rework.started', $rework);
            DB::afterCommit(fn () => event(new ReworkStarted($rework->id, $rework->work_order_id)));

            return $rework;
        });
    }

    public function completeService(ReworkOrder $rework, int $reworkServiceId): ReworkOrder
    {
        return DB::transaction(function () use ($rework, $reworkServiceId) {
            $rework = $this->lockScoped($rework);
            if ($rework->status !== 'in_progress') {
                throw new BusinessRuleException('The rework order is not active.');
            }
            $service = $rework->services()->whereKey($reworkServiceId)->lockForUpdate()->firstOrFail();
            $service->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            return $rework;
        });
    }

    public function complete(ReworkOrder $rework): ReworkOrder
    {
        return DB::transaction(function () use ($rework) {
            $rework = $this->lockScoped($rework);
            if ($rework->status !== 'in_progress'
                || $rework->services()->whereNotIn('status', ['completed', 'cancelled'])->exists()
                || $rework->materials()->whereIn('status', ['planned', 'reserved', 'issued', 'partially_used'])->exists()) {
                throw new BusinessRuleException('Complete every rework service and settle all materials first.');
            }
            $this->costs->rebuild($rework);
            $rework->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            if ($rework->warranty_claim_id) {
                $claim = WarrantyClaim::query()->whereKey($rework->warranty_claim_id)->lockForUpdate()->firstOrFail();
                $claim->forceFill([
                    'status' => 'under_review',
                    'resolution_notes' => "Rework {$rework->rework_number} completed; final quality review required.",
                    'actual_company_cost' => $claim->reworkOrders()->where('status', 'completed')->sum('total_rework_cost'),
                ])->save();
            } else {
                $order = WorkOrder::query()->whereKey($rework->work_order_id)->lockForUpdate()->firstOrFail();
                $linkedIds = $rework->services()->pluck('work_order_service_id');
                $order->services()->whereIn('id', $linkedIds)->update(['status' => 'completed', 'completed_at' => now()]);
                $from = $order->status;
                $order->forceFill(['status' => 'awaiting_quality', 'ready_for_quality_at' => now(), 'finished_at' => now()])->save();
                $order->statusLogs()->create([
                    'from_status' => $from, 'to_status' => 'awaiting_quality',
                    'reason' => "Rework {$rework->rework_number} completed",
                    'changed_by' => $this->tenant->user()->id,
                ]);
                $this->workOrderCosts->rebuild($order);
            }
            $this->audit->record('rework.completed', $rework, ['total_cost' => $rework->total_rework_cost]);
            DB::afterCommit(fn () => event(new ReworkCompleted($rework->id, $rework->work_order_id)));

            return $rework->refresh();
        });
    }

    private function lockScoped(ReworkOrder $rework): ReworkOrder
    {
        $rework = ReworkOrder::query()->whereKey($rework->id)->lockForUpdate()->firstOrFail();
        abort_unless(
            (int) $rework->company_id === (int) $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($rework->workOrder->branch),
            403
        );

        return $rework;
    }
}
