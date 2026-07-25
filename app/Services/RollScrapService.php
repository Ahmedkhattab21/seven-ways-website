<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

class RollScrapService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private RollService $rolls,
        private StockMovementService $stockMovements,
        private AuditService $audit
    ) {
    }

    public function create(InventoryRoll $roll, string $width, string $length, array $data = []): RollScrap
    {
        return DB::transaction(function () use ($roll, $width, $length, $data) {
            $roll = InventoryRoll::query()->whereKey($roll->id)->lockForUpdate()->firstOrFail();
            $area = bcmul($width, $length, 6);
            if (! in_array($roll->status, ['available', 'opened'], true)
                || bccomp($area, '0', 6) <= 0 || bccomp($width, $roll->width, 6) === 1
                || bccomp($area, $roll->remaining_area, 6) === 1) {
                throw new BusinessRuleException('Scrap dimensions must be positive.');
            }
            $areaBefore = $roll->remaining_area;
            $lengthBefore = $roll->remaining_length;
            $areaAfter = bcsub($areaBefore, $area, 6);
            $lengthAfter = bcdiv($areaAfter, $roll->width, 6);
            $finished = bccomp($areaAfter, config('inventory.roll_tolerance'), 6) <= 0;
            $roll->forceFill([
                'remaining_area' => $finished ? '0' : $areaAfter,
                'remaining_length' => $finished ? '0' : $lengthAfter,
                'status' => $finished ? 'finished' : 'opened',
                'opened_at' => $roll->opened_at ?? now(), 'finished_at' => $finished ? now() : null,
            ])->save();
            $scrap = new RollScrap($data);
            $scrap->forceFill([
                'company_id' => $roll->company_id, 'branch_id' => $roll->branch_id, 'warehouse_id' => $roll->warehouse_id,
                'source_roll_id' => $roll->id, 'scrap_code' => $this->numbers->next('roll_scrap', $roll->company_id, $roll->branch_id),
                'width' => $width, 'length' => $length, 'area' => $area,
                'unit_cost_per_area' => $roll->unit_cost_per_area,
                'total_cost' => bcmul($area, $roll->unit_cost_per_area, 4), 'status' => 'available',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->rolls->recordMovement($roll, 'scrap_create', $lengthBefore, $roll->remaining_length, $areaBefore, $roll->remaining_area, [
                'type' => 'roll_scrap', 'id' => $scrap->id, 'usable_area' => $area,
            ]);
            $this->recordStockMovement($scrap, 'roll_scrap_created');
            $this->audit->record('roll_scrap.created', $scrap, ['source_roll_id' => $roll->id]);

            return $scrap;
        });
    }

    public function consume(RollScrap $scrap, array $context = []): void
    {
        DB::transaction(function () use ($scrap, $context) {
            $scrap = RollScrap::query()->whereKey($scrap->id)->lockForUpdate()->firstOrFail();
            if ($scrap->status !== 'available') {
                throw new BusinessRuleException('Scrap is not available.');
            }
            $scrap->forceFill(['status' => 'consumed', 'consumed_at' => now()])->save();
            $this->recordStockMovement($scrap, 'roll_scrap_consumed', $context);
            $this->audit->record('roll_scrap.consumed', $scrap);
        });
    }

    private function recordStockMovement(RollScrap $scrap, string $type, array $context = []): void
    {
        $roll = InventoryRoll::findOrFail($scrap->source_roll_id);
        $product = Product::findOrFail($roll->product_id);
        $balance = StockBalance::query()->where('warehouse_id', $scrap->warehouse_id)
            ->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
        $this->stockMovements->record([
            'company_id' => $scrap->company_id, 'branch_id' => $scrap->branch_id,
            'warehouse_id' => $scrap->warehouse_id, 'product_id' => $product->id,
            'movement_type' => $type, 'direction' => 'none',
            'reference_type' => $context['type'] ?? 'roll_scrap',
            'reference_id' => $context['id'] ?? $scrap->id,
            'quantity' => $scrap->area, 'unit_id' => $product->stock_unit_id,
            'stock_quantity' => '0', 'unit_cost' => $scrap->unit_cost_per_area, 'total_cost' => $scrap->total_cost,
            'balance_before' => $balance->quantity, 'balance_after' => $balance->quantity,
        ]);
    }
}
