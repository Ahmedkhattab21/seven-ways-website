<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryReservation;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferShipmentService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private InventoryService $inventory,
        private StockMovementService $movements,
        private RollService $rollMovements,
        private AuditService $audit
    ) {
    }

    public function ship(StockTransfer $transfer, ?string $shippingReference = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $shippingReference) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'ready_to_ship'
                || ! $this->tenant->accessibleBranches()->contains('id', $transfer->from_branch_id)) {
                throw new BusinessRuleException('Transfer cannot be shipped from this branch.', status: 403);
            }
            $items = $transfer->items()->with(['product', 'roll', 'scrap.sourceRoll'])->orderBy('id')->lockForUpdate()->get();
            $activeReservations = InventoryReservation::query()->where('reference_type', 'stock_transfer')
                ->where('reference_id', $transfer->id)->where('status', 'active')
                ->orderBy('id')->lockForUpdate()->get();
            $source = Warehouse::findOrFail($transfer->from_warehouse_id);
            $transit = Warehouse::query()->whereKey($transfer->transit_warehouse_id)
                ->where('is_system', true)->where('warehouse_type', 'transit')->lockForUpdate()->firstOrFail();

            foreach ($items as $item) {
                $reservation = $this->reservationFor($activeReservations, $item);
                if (! $reservation) {
                    throw new BusinessRuleException('Active reservation is missing for a transfer item.');
                }
                $activeReservations = $activeReservations->reject(
                    fn (InventoryReservation $candidate) => $candidate->id === $reservation->id
                );
                $this->reservations->consume($reservation);
                $this->shipItem($transfer, $item, $source, $transit);
            }
            $transfer->forceFill([
                'status' => 'shipped', 'shipped_by' => $this->tenant->user()->id,
                'shipped_at' => now(), 'shipping_reference' => $shippingReference,
            ])->save();
            $this->audit->record('stock_transfer.shipped', $transfer);

            return $transfer;
        });
    }

    private function shipItem(StockTransfer $transfer, StockTransferItem $item, Warehouse $source, Warehouse $transit): void
    {
        $reference = ['type' => 'stock_transfer', 'id' => $transfer->id];
        $quantity = $item->prepared_quantity;
        if ($item->item_type === 'quantity') {
            $balance = StockBalance::query()->where('warehouse_id', $source->id)
                ->where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
            $cost = $balance->average_cost;
            $this->inventory->issue($source, $item->product, $quantity, 'transfer_out', $reference);
            $this->inventory->receive($transit, $item->product, $quantity, $cost, 'transfer_in_transit', $reference);
            $item->forceFill([
                'shipped_quantity' => $quantity, 'unit_cost' => $cost,
                'total_cost' => bcmul($quantity, $cost, 4),
            ])->save();

            return;
        }
        if ($item->item_type === 'roll') {
            $roll = InventoryRoll::query()->whereKey($item->roll_id)->where('warehouse_id', $source->id)
                ->whereIn('status', ['available', 'opened'])->lockForUpdate()->firstOrFail();
            $this->inventory->issue($source, $item->product, '1', 'transfer_out', $reference);
            $this->inventory->receive($transit, $item->product, '1', $roll->total_cost, 'transfer_in_transit', $reference);
            $this->rollMovements->recordMovement($roll, 'transfer_out', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference);
            $roll->forceFill(['warehouse_id' => $transit->id, 'branch_id' => $transit->branch_id, 'status' => 'available'])->save();
            $this->rollMovements->recordMovement($roll, 'transfer_in_transit', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference);
            $item->forceFill(['shipped_quantity' => 1, 'unit_cost' => $roll->total_cost, 'total_cost' => $roll->total_cost])->save();

            return;
        }
        $scrap = RollScrap::query()->whereKey($item->scrap_id)->where('warehouse_id', $source->id)
            ->where('status', 'available')->lockForUpdate()->firstOrFail();
        $this->recordScrapMovement($transfer, $item->product, $scrap, $source, 'transfer_out');
        $scrap->forceFill(['warehouse_id' => $transit->id, 'branch_id' => $transit->branch_id])->save();
        $this->recordScrapMovement($transfer, $item->product, $scrap, $transit, 'transfer_in_transit');
        $item->forceFill(['shipped_quantity' => 1, 'unit_cost' => $scrap->total_cost, 'total_cost' => $scrap->total_cost])->save();
    }

    private function reservationFor($reservations, StockTransferItem $item): ?InventoryReservation
    {
        return $reservations->first(function (InventoryReservation $reservation) use ($item) {
            if ($item->item_type === 'roll') {
                return $reservation->inventory_roll_id === $item->roll_id;
            }
            if ($item->item_type === 'scrap') {
                return $reservation->roll_scrap_id === $item->scrap_id;
            }

            return $reservation->product_id === $item->product_id && $reservation->quantity === $item->approved_quantity;
        });
    }

    private function recordScrapMovement(StockTransfer $transfer, Product $product, RollScrap $scrap, Warehouse $warehouse, string $type): void
    {
        $balance = StockBalance::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
            ['company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id]
        )->refresh();
        $this->movements->record([
            'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'movement_type' => $type, 'direction' => 'none', 'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id, 'quantity' => $scrap->area, 'unit_id' => $product->stock_unit_id,
            'stock_quantity' => 0, 'unit_cost' => $scrap->unit_cost_per_area, 'total_cost' => $scrap->total_cost,
            'balance_before' => $balance->quantity, 'balance_after' => $balance->quantity,
        ]);
    }
}
