<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryRoll;
use App\Models\RollScrap;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferReversalService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private InventoryService $inventory,
        private StockMovementService $movements,
        private RollService $rollMovements,
        private AuditService $audit
    ) {
    }

    public function reverse(StockTransfer $original): StockTransfer
    {
        return DB::transaction(function () use ($original) {
            $original = StockTransfer::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'received' || $original->reversals()->exists()
                || $original->company_id !== $this->tenant->companyId()
                || $original->discrepancies()->exists()) {
                throw new BusinessRuleException('Only a fully received transfer without discrepancies can be reversed once.');
            }
            $items = $original->items()->with('product')->orderBy('id')->lockForUpdate()->get();
            $from = Warehouse::findOrFail($original->to_warehouse_id);
            $to = Warehouse::findOrFail($original->from_warehouse_id);
            $reversal = new StockTransfer;
            $reversal->forceFill([
                'company_id' => $original->company_id,
                'transfer_number' => $this->numbers->next('stock_transfer', $original->company_id, $from->branch_id),
                'transfer_type' => $from->branch_id === $to->branch_id ? 'internal' : 'inter_branch',
                'from_branch_id' => $from->branch_id, 'from_warehouse_id' => $from->id,
                'to_branch_id' => $to->branch_id, 'to_warehouse_id' => $to->id,
                'status' => 'received', 'requested_by' => $this->tenant->user()->id,
                'requested_at' => now(), 'approved_by' => $this->tenant->user()->id, 'approved_at' => now(),
                'prepared_by' => $this->tenant->user()->id, 'prepared_at' => now(),
                'shipped_by' => $this->tenant->user()->id, 'shipped_at' => now(),
                'received_by' => $this->tenant->user()->id, 'received_at' => now(),
                'reversal_of_id' => $original->id, 'notes' => 'Formal reversal of '.$original->transfer_number,
            ])->save();

            foreach ($items as $item) {
                $quantity = $item->received_quantity;
                if (bccomp($quantity, '0', 6) <= 0) {
                    continue;
                }
                $newItem = $reversal->items()->create([
                    'product_id' => $item->product_id, 'item_type' => $item->item_type,
                    'roll_id' => $item->roll_id, 'scrap_id' => $item->scrap_id,
                    'requested_quantity' => $quantity, 'approved_quantity' => $quantity,
                    'prepared_quantity' => $quantity, 'shipped_quantity' => $quantity,
                    'received_quantity' => $quantity, 'unit_id' => $item->unit_id,
                    'unit_cost' => $item->unit_cost, 'total_cost' => bcmul($quantity, $item->unit_cost, 4),
                ]);
                $this->reverseItem($reversal, $newItem, $from, $to);
            }
            if (! $reversal->items()->exists()) {
                throw new BusinessRuleException('Transfer has no received quantity to reverse.');
            }
            $original->forceFill(['status' => 'reversed'])->save();
            $this->audit->record('stock_transfer.reversed', $original, ['reversal_id' => $reversal->id]);

            return $reversal;
        });
    }

    private function reverseItem(StockTransfer $transfer, $item, Warehouse $from, Warehouse $to): void
    {
        $reference = ['type' => 'stock_transfer', 'id' => $transfer->id];
        if ($item->item_type === 'quantity') {
            $outReference = $reference + [
                'reversal_of_id' => $this->originalMovement($transfer, $item, $from, 'transfer_in')->id,
            ];
            $inReference = $reference + [
                'reversal_of_id' => $this->originalMovement($transfer, $item, $to, 'transfer_out')->id,
            ];
            $this->inventory->issue($from, $item->product, $item->received_quantity, 'transfer_reversal_out', $outReference);
            $this->inventory->receive($to, $item->product, $item->received_quantity, $item->unit_cost, 'transfer_reversal_in', $inReference);

            return;
        }
        if ($item->item_type === 'roll') {
            $roll = InventoryRoll::query()->whereKey($item->roll_id)->where('warehouse_id', $from->id)
                ->whereIn('status', ['available', 'opened'])->lockForUpdate()->firstOrFail();
            $outReference = $reference + [
                'reversal_of_id' => $this->originalMovement($transfer, $item, $from, 'transfer_in')->id,
            ];
            $inReference = $reference + [
                'reversal_of_id' => $this->originalMovement($transfer, $item, $to, 'transfer_out')->id,
            ];
            $this->inventory->issue($from, $item->product, '1', 'transfer_reversal_out', $outReference);
            $this->inventory->receive($to, $item->product, '1', $roll->total_cost, 'transfer_reversal_in', $inReference);
            $originalOut = $roll->movements()->where('reference_type', 'stock_transfer')
                ->where('reference_id', $transfer->reversal_of_id)->where('movement_type', 'transfer_in')
                ->latest('id')->firstOrFail();
            $this->rollMovements->recordMovement($roll, 'transfer_reversal_out', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference + ['reversal_of_id' => $originalOut->id]);
            $roll->forceFill(['warehouse_id' => $to->id, 'branch_id' => $to->branch_id])->save();
            $originalIn = $roll->movements()->where('reference_type', 'stock_transfer')
                ->where('reference_id', $transfer->reversal_of_id)->where('movement_type', 'transfer_out')
                ->latest('id')->firstOrFail();
            $this->rollMovements->recordMovement($roll, 'transfer_reversal_in', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference + ['reversal_of_id' => $originalIn->id]);

            return;
        }
        $scrap = RollScrap::query()->whereKey($item->scrap_id)->where('warehouse_id', $from->id)
            ->where('status', 'available')->lockForUpdate()->firstOrFail();
        $this->recordScrapMovement($transfer, $item, $scrap, $from, 'transfer_reversal_out');
        $scrap->forceFill(['warehouse_id' => $to->id, 'branch_id' => $to->branch_id])->save();
        $this->recordScrapMovement($transfer, $item, $scrap, $to, 'transfer_reversal_in');
    }

    private function recordScrapMovement(StockTransfer $transfer, $item, RollScrap $scrap, Warehouse $warehouse, string $type): void
    {
        $balance = StockBalance::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $item->product_id],
            ['company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id]
        )->refresh();
        $this->movements->record([
            'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'product_id' => $item->product_id,
            'movement_type' => $type, 'direction' => 'none', 'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id, 'quantity' => $scrap->area, 'unit_id' => $item->unit_id,
            'stock_quantity' => 0, 'unit_cost' => $scrap->unit_cost_per_area, 'total_cost' => $scrap->total_cost,
            'balance_before' => $balance->quantity, 'balance_after' => $balance->quantity,
            'reversal_of_id' => $this->originalMovement($transfer, $item, $warehouse, $type === 'transfer_reversal_out' ? 'transfer_in' : 'transfer_out')->id,
        ]);
    }

    private function originalMovement(StockTransfer $transfer, $item, Warehouse $warehouse, string $type): StockMovement
    {
        return StockMovement::query()
            ->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->reversal_of_id)
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $item->product_id)
            ->where('movement_type', $type)
            ->whereDoesntHave('reversals')
            ->orderBy('id')
            ->lockForUpdate()
            ->firstOrFail();
    }
}
