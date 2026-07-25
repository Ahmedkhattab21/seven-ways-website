<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderCreated;
use App\Models\Appointment;
use App\Models\Quotation;
use App\Models\VehicleInspection;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderCreationService
{
    public function __construct(private TenantContext $tenant, private DocumentNumberService $numbers)
    {
    }

    public function fromAppointment(Appointment $appointment, int $warehouseId): WorkOrder
    {
        return DB::transaction(function () use ($appointment, $warehouseId) {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status !== 'checked_in') {
                throw new BusinessRuleException('Only a checked-in appointment can create a work order.');
            }
            $this->assertNoActiveAppointmentOrder($appointment->id);
            $appointment->load(['services', 'quotation.items.materials']);
            $order = $this->createOrder([
                'branch_id' => $appointment->branch_id, 'warehouse_id' => $warehouseId,
                'appointment_id' => $appointment->id, 'quotation_id' => $appointment->quotation_id,
                'customer_id' => $appointment->customer_id, 'vehicle_id' => $appointment->vehicle_id,
                'priority' => $appointment->priority, 'check_in_at' => $appointment->checked_in_at ?? now(),
                'odometer_at_check_in' => $appointment->odometer_snapshot,
                'customer_notes' => $appointment->customer_notes, 'internal_notes' => $appointment->internal_notes,
                'estimated_subtotal' => $appointment->quotation?->subtotal ?? $appointment->services->sum('total_snapshot'),
                'estimated_tax' => $appointment->quotation?->tax_amount ?? 0,
                'estimated_total' => $appointment->quotation?->total ?? $appointment->services->sum('total_snapshot'),
                'estimated_material_cost' => $appointment->quotation?->estimated_material_cost,
                'estimated_margin' => $appointment->quotation?->estimated_margin,
            ]);
            foreach ($appointment->services as $line) {
                $serviceLine = $order->services()->create([
                    'uuid' => (string) Str::uuid(), 'appointment_service_id' => $line->id,
                    'quotation_item_id' => $line->quotation_item_id, 'service_id' => $line->service_id,
                    'service_package_id' => $line->service_package_id, 'description' => $line->description,
                    'quantity' => $line->quantity, 'planned_duration_minutes' => $line->estimated_duration_minutes,
                    'unit_price_snapshot' => $line->unit_price_snapshot, 'total_snapshot' => $line->total_snapshot,
                    'sort_order' => $line->sort_order,
                ]);
                $materials = $appointment->quotation?->items->firstWhere('id', $line->quotation_item_id)?->materials ?? collect();
                foreach ($materials as $material) {
                    $serviceLine->materials()->create([
                        'uuid' => (string) Str::uuid(), 'work_order_id' => $order->id,
                        'product_id' => $material->product_id, 'warehouse_id' => $warehouseId,
                        'material_type' => 'quantity', 'expected_quantity' => $material->expected_quantity,
                        'unit_id' => $material->unit_id, 'unit_cost' => $material->estimated_unit_cost ?? 0,
                    ]);
                }
            }
            $appointment->forceFill(['status' => 'in_progress', 'updated_by' => $this->tenant->user()->id])->save();
            DB::afterCommit(fn () => event(new WorkOrderCreated($order->id)));

            return $order->load('inspection', 'services.materials');
        });
    }

    public function fromQuotation(Quotation $quotation, int $warehouseId): WorkOrder
    {
        return DB::transaction(function () use ($quotation, $warehouseId) {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->with('items.materials')->firstOrFail();
            if ($quotation->status !== 'accepted' || WorkOrder::where('quotation_id', $quotation->id)->exists()) {
                throw new BusinessRuleException('Quotation must be accepted and unused.');
            }
            $order = $this->createOrder([
                'branch_id' => $quotation->branch_id, 'warehouse_id' => $warehouseId,
                'quotation_id' => $quotation->id, 'customer_id' => $quotation->customer_id,
                'vehicle_id' => $quotation->vehicle_id, 'estimated_subtotal' => $quotation->subtotal,
                'estimated_tax' => $quotation->tax_amount, 'estimated_total' => $quotation->total,
                'estimated_material_cost' => $quotation->estimated_material_cost, 'estimated_margin' => $quotation->estimated_margin,
                'customer_notes' => $quotation->customer_notes, 'internal_notes' => $quotation->internal_notes,
            ]);
            foreach ($quotation->items->where('item_type', 'service') as $line) {
                $serviceLine = $order->services()->create([
                    'uuid' => (string) Str::uuid(), 'quotation_item_id' => $line->id,
                    'service_id' => $line->service_id, 'service_package_id' => $line->service_package_id,
                    'description' => $line->description, 'quantity' => $line->quantity,
                    'planned_duration_minutes' => $line->estimated_duration_minutes ?? 0,
                    'unit_price_snapshot' => $line->unit_price, 'total_snapshot' => $line->total,
                    'estimated_material_cost' => $line->estimated_material_cost, 'sort_order' => $line->sort_order,
                ]);
                foreach ($line->materials as $material) {
                    $serviceLine->materials()->create([
                        'uuid' => (string) Str::uuid(), 'work_order_id' => $order->id,
                        'product_id' => $material->product_id, 'warehouse_id' => $warehouseId,
                        'material_type' => 'quantity', 'expected_quantity' => $material->expected_quantity,
                        'unit_id' => $material->unit_id, 'unit_cost' => $material->estimated_unit_cost ?? 0,
                    ]);
                }
            }
            DB::afterCommit(fn () => event(new WorkOrderCreated($order->id)));

            return $order;
        });
    }

    public function direct(array $data, array $services): WorkOrder
    {
        return DB::transaction(function () use ($data, $services) {
            $order = $this->createOrder($data);
            foreach ($services as $index => $line) {
                $order->services()->create([
                    'uuid' => (string) Str::uuid(), 'service_id' => $line['service_id'],
                    'description' => $line['description'], 'quantity' => $line['quantity'] ?? 1,
                    'planned_duration_minutes' => $line['planned_duration_minutes'] ?? 0,
                    'unit_price_snapshot' => $line['unit_price_snapshot'] ?? 0,
                    'total_snapshot' => bcmul((string) ($line['quantity'] ?? 1), (string) ($line['unit_price_snapshot'] ?? 0), 4),
                    'sort_order' => $index,
                ]);
            }
            DB::afterCommit(fn () => event(new WorkOrderCreated($order->id)));

            return $order;
        });
    }

    private function createOrder(array $data): WorkOrder
    {
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        if ($warehouse->company_id !== $this->tenant->companyId() || $warehouse->branch_id !== (int) $data['branch_id']
            || ! $warehouse->is_active || $warehouse->is_system || ! $warehouse->allows_work_order_issue) {
            throw new BusinessRuleException('Select an active normal work-order warehouse in the same branch.');
        }
        $order = new WorkOrder($data);
        $order->forceFill([
            'uuid' => (string) Str::uuid(), 'company_id' => $this->tenant->companyId(),
            'work_order_number' => $this->numbers->next('work_order', $this->tenant->companyId(), $data['branch_id']),
            'status' => 'awaiting_inspection', 'created_by' => $this->tenant->user()->id,
        ])->save();
        $order->statusLogs()->create(['from_status' => null, 'to_status' => 'awaiting_inspection', 'changed_by' => $this->tenant->user()->id]);
        VehicleInspection::query()->create([
            'uuid' => (string) Str::uuid(), 'company_id' => $order->company_id, 'branch_id' => $order->branch_id,
            'work_order_id' => $order->id, 'vehicle_id' => $order->vehicle_id, 'inspection_type' => 'check_in',
            'status' => 'draft', 'odometer' => $order->odometer_at_check_in, 'fuel_level' => $order->fuel_level,
        ]);

        return $order;
    }

    private function assertNoActiveAppointmentOrder(int $appointmentId): void
    {
        if (WorkOrder::where('appointment_id', $appointmentId)->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)->exists()) {
            throw new BusinessRuleException('This appointment already has an active work order.');
        }
    }
}
