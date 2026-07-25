<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class RollConsumptionService
{
    public function __construct(
        private RollService $rolls,
        private InventoryService $inventory,
        private StockMovementService $stockMovements,
        private AuditService $audit
    ) {
    }

    public function consume(InventoryRoll $roll, string $length, string $usableArea, string $wasteArea = '0', array $context = []): InventoryRoll
    {
        if (bccomp($length, '0', 6) <= 0 || bccomp($usableArea, '0', 6) < 0 || bccomp($wasteArea, '0', 6) < 0) {
            throw new BusinessRuleException('Consumption values are invalid.');
        }

        return DB::transaction(function () use ($roll, $length, $usableArea, $wasteArea, $context) {
            $roll = InventoryRoll::query()->whereKey($roll->id)->lockForUpdate()->firstOrFail();
            if (! in_array($roll->status, ['available', 'opened'], true)) {
                throw new BusinessRuleException('This roll cannot be consumed in its current status.');
            }
            if (bccomp($length, $roll->remaining_length, 6) === 1) {
                throw new BusinessRuleException('Consumption exceeds remaining roll length.');
            }
            $area = bcmul($roll->width, $length, 6);
            if (bccomp(bcadd($usableArea, $wasteArea, 6), $area, 6) === 1) {
                throw new BusinessRuleException('Usable and waste area exceed the consumed area.');
            }
            $lengthBefore = $roll->remaining_length;
            $areaBefore = $roll->remaining_area;
            $lengthAfter = bcsub($lengthBefore, $length, 6);
            $areaAfter = bcsub($areaBefore, $area, 6);
            $finished = bccomp($lengthAfter, config('inventory.roll_tolerance'), 6) <= 0;
            $roll->forceFill([
                'remaining_length' => $finished ? '0' : $lengthAfter,
                'remaining_area' => $finished ? '0' : $areaAfter,
                'status' => $finished ? 'finished' : 'opened',
                'opened_at' => $roll->opened_at ?? now(), 'finished_at' => $finished ? now() : null,
            ])->save();
            $type = bccomp($wasteArea, '0', 6) === 1 && bccomp($usableArea, '0', 6) === 0 ? 'waste' : 'consume';
            $this->rolls->recordMovement($roll, $type, $lengthBefore, $roll->remaining_length, $areaBefore, $roll->remaining_area, $context + [
                'usable_area' => $usableArea, 'waste_area' => $wasteArea,
            ]);
            $product = Product::findOrFail($roll->product_id);
            $balance = StockBalance::query()->where('warehouse_id', $roll->warehouse_id)->where('product_id', $roll->product_id)->lockForUpdate()->firstOrFail();
            $this->stockMovements->record([
                'company_id' => $roll->company_id, 'branch_id' => $roll->branch_id,
                'warehouse_id' => $roll->warehouse_id, 'product_id' => $roll->product_id,
                'movement_type' => $type === 'waste' ? 'roll_waste' : 'roll_consumption', 'direction' => 'none',
                'reference_type' => 'roll', 'reference_id' => $roll->id, 'quantity' => $area,
                'unit_id' => $product->stock_unit_id, 'stock_quantity' => '0',
                'unit_cost' => $roll->unit_cost_per_area, 'total_cost' => bcmul($area, $roll->unit_cost_per_area, 4),
                'balance_before' => $balance->quantity, 'balance_after' => $balance->quantity,
            ]);
            if ($finished) {
                $this->inventory->issue(Warehouse::findOrFail($roll->warehouse_id), $product, '1', 'roll_consumption', ['type' => 'roll', 'id' => $roll->id]);
            }
            $this->audit->record($type === 'waste' ? 'roll.wasted' : 'roll.consumed', $roll, ['area' => $area]);

            return $roll;
        });
    }
}
