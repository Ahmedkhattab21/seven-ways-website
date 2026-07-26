<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RollService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private InventoryService $inventory,
        private AuditService $audit,
        private MoneyRoundingService $rounding
    ) {
    }

    public function receive(Warehouse $warehouse, Product $product, array $data, array $reference = []): InventoryRoll
    {
        if ($product->tracking_type !== 'roll' || $product->costing_method !== 'specific') {
            throw new BusinessRuleException('Only roll-tracked products with specific costing can create rolls.');
        }
        if ($warehouse->company_id !== $product->company_id || $product->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Roll scope is invalid.', status: 403);
        }
        $width = (string) $data['width'];
        $length = (string) $data['original_length'];
        if (bccomp($width, '0', 6) <= 0 || bccomp($length, '0', 6) <= 0) {
            throw new BusinessRuleException('Roll dimensions must be positive.');
        }

        return DB::transaction(function () use ($warehouse, $product, $data, $reference, $width, $length) {
            $area = bcmul($width, $length, 6);
            $total = (string) $data['total_cost'];
            $cost = $this->rounding->round(bcdiv($total, $area, 8), 4);
            $roll = new InventoryRoll($data);
            $roll->forceFill([
                'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
                'roll_number' => $data['roll_number'] ?? $this->numbers->next('roll', $warehouse->company_id, $warehouse->branch_id),
                'original_length' => $length, 'remaining_length' => $length,
                'original_area' => $area, 'remaining_area' => $area, 'unit_cost_per_area' => $cost,
                'received_at' => $data['received_at'] ?? now(), 'status' => 'available',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->recordMovement($roll, 'receipt', '0', $length, '0', $area, $reference);
            $this->inventory->receive(
                $warehouse,
                $product,
                '1',
                $total,
                $reference['movement_type'] ?? 'roll_receipt',
                ['type' => $reference['type'] ?? 'roll', 'id' => $reference['id'] ?? $roll->id]
            );
            $this->audit->record('roll.received', $roll, ['area' => $area]);

            return $roll;
        });
    }

    public function recordMovement(InventoryRoll $roll, string $type, string $lengthBefore, string $lengthAfter, string $areaBefore, string $areaAfter, array $context = []): RollMovement
    {
        $lengthChange = bcsub($lengthBefore, $lengthAfter, 6);
        $areaChange = bcsub($areaBefore, $areaAfter, 6);
        $usable = (string) ($context['usable_area'] ?? '0');
        $waste = (string) ($context['waste_area'] ?? '0');
        $movement = new RollMovement;
        $movement->forceFill([
            'company_id' => $roll->company_id, 'branch_id' => $roll->branch_id, 'warehouse_id' => $roll->warehouse_id,
            'inventory_roll_id' => $roll->id,
            'movement_number' => $this->numbers->next('stock_movement', $roll->company_id, $roll->branch_id),
            'movement_type' => $type, 'reference_type' => $context['type'] ?? null, 'reference_id' => $context['id'] ?? null,
            'length_before' => $lengthBefore, 'length_change' => bccomp($lengthChange, '0', 6) < 0 ? bcsub('0', $lengthChange, 6) : $lengthChange, 'length_after' => $lengthAfter,
            'area_before' => $areaBefore, 'area_change' => bccomp($areaChange, '0', 6) < 0 ? bcsub('0', $areaChange, 6) : $areaChange, 'area_after' => $areaAfter,
            'usable_area' => $usable, 'waste_area' => $waste, 'unit_cost_per_area' => $roll->unit_cost_per_area,
            'usable_cost' => bcmul($usable, $roll->unit_cost_per_area, 4),
            'waste_cost' => bcmul($waste, $roll->unit_cost_per_area, 4),
            'employee_id' => $context['employee_id'] ?? null, 'reason' => $context['reason'] ?? null,
            'notes' => $context['notes'] ?? null, 'occurred_at' => now(), 'created_by' => $this->tenant->user()->id,
            'reversal_of_id' => $context['reversal_of_id'] ?? null,
            'uuid' => (string) Str::uuid(),
        ])->save();

        return $movement;
    }
}
