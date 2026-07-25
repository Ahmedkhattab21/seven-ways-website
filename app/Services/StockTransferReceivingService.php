<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryRoll;
use App\Models\RollScrap;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferReceivingService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryService $inventory,
        private StockMovementService $movements,
        private RollService $rollMovements,
        private TransferDiscrepancyService $discrepancies,
        private AuditService $audit
    ) {
    }

    public function receive(StockTransfer $transfer, array $lines): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $lines) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['shipped', 'partially_received'], true)
                || ! $this->tenant->accessibleBranches()->contains('id', $transfer->to_branch_id)) {
                throw new BusinessRuleException('Transfer cannot be received into this branch.', status: 403);
            }
            $items = $transfer->items()->with('product')->orderBy('id')->lockForUpdate()->get();
            $transit = Warehouse::query()->whereKey($transfer->transit_warehouse_id)->where('is_system', true)->lockForUpdate()->firstOrFail();
            $destination = Warehouse::query()->whereKey($transfer->to_warehouse_id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $damagedWarehouse = null;

            foreach ($items as $item) {
                $line = $lines[$item->id] ?? [];
                $received = (string) ($line['received_quantity'] ?? 0);
                $damaged = (string) ($line['damaged_quantity'] ?? 0);
                $shortage = (string) ($line['shortage_quantity'] ?? 0);
                $rejected = (string) ($line['rejected_quantity'] ?? 0);
                foreach ([$received, $damaged, $shortage, $rejected] as $value) {
                    if (bccomp($value, '0', 6) < 0) {
                        throw new BusinessRuleException('Receiving quantities cannot be negative.');
                    }
                }
                $previous = bcadd(bcadd($item->received_quantity, $item->damaged_quantity, 6), bcadd($item->shortage_quantity, $item->rejected_quantity, 6), 6);
                $current = bcadd(bcadd($received, $damaged, 6), bcadd($shortage, $rejected, 6), 6);
                if (bccomp(bcadd($previous, $current, 6), $item->shipped_quantity, 6) === 1) {
                    throw new BusinessRuleException('Received and discrepancy quantities exceed shipped quantity.');
                }
                if (bccomp($current, '0', 6) === 0) {
                    continue;
                }
                if (bccomp($damaged, '0', 6) === 1) {
                    $damagedWarehouse ??= Warehouse::query()->where('company_id', $transfer->company_id)
                        ->where('branch_id', $transfer->to_branch_id)->whereIn('warehouse_type', ['damaged', 'quarantine'])
                        ->where('is_active', true)->where('is_system', false)->lockForUpdate()->first();
                    if (! $damagedWarehouse) {
                        throw new BusinessRuleException('Destination branch requires an active damaged or quarantine warehouse.');
                    }
                }
                $this->receiveItem($transfer, $item, $transit, $destination, $damagedWarehouse, $received, $damaged, $shortage);
                $item->forceFill([
                    'received_quantity' => bcadd($item->received_quantity, $received, 6),
                    'damaged_quantity' => bcadd($item->damaged_quantity, $damaged, 6),
                    'shortage_quantity' => bcadd($item->shortage_quantity, $shortage, 6),
                    'rejected_quantity' => bcadd($item->rejected_quantity, $rejected, 6),
                ])->save();
                foreach (['damage' => $damaged, 'shortage' => $shortage, 'rejection' => $rejected] as $type => $quantity) {
                    if (bccomp($quantity, '0', 6) === 1) {
                        $this->discrepancies->report($transfer, $item, [
                            'discrepancy_type' => $type, 'quantity' => $quantity,
                            'description' => ucfirst($type).' reported during receiving.',
                        ]);
                    }
                }
            }
            $complete = $items->every(function (StockTransferItem $item) {
                $item->refresh();
                $processed = bcadd(
                    bcadd($item->received_quantity, $item->damaged_quantity, 6),
                    $item->shortage_quantity,
                    6
                );

                return bccomp($processed, $item->shipped_quantity, 6) >= 0;
            });
            $transfer->forceFill([
                'status' => $complete ? 'received' : 'partially_received',
                'received_by' => $this->tenant->user()->id,
                'received_at' => $complete ? now() : null,
            ])->save();
            $this->audit->record($complete ? 'stock_transfer.received' : 'stock_transfer.partially_received', $transfer);

            return $transfer;
        });
    }

    private function receiveItem(
        StockTransfer $transfer,
        StockTransferItem $item,
        Warehouse $transit,
        Warehouse $destination,
        ?Warehouse $damagedWarehouse,
        string $received,
        string $damaged,
        string $shortage
    ): void {
        $reference = ['type' => 'stock_transfer', 'id' => $transfer->id];
        if ($item->item_type === 'quantity') {
            foreach ([[$received, $destination, 'transfer_in'], [$damaged, $damagedWarehouse, 'transfer_damaged_in']] as [$quantity, $warehouse, $type]) {
                if (bccomp($quantity, '0', 6) === 1) {
                    $this->inventory->issueFromSystemTransit($transit, $item->product, $quantity, 'transfer_out_transit', $reference);
                    $this->inventory->receive($warehouse, $item->product, $quantity, $item->unit_cost, $type, $reference);
                }
            }
            if (bccomp($shortage, '0', 6) === 1) {
                $this->inventory->issueFromSystemTransit($transit, $item->product, $shortage, 'transfer_out_transit', $reference);
            }

            return;
        }
        $accepted = bccomp($received, '0', 6) === 1;
        $isDamaged = bccomp($damaged, '0', 6) === 1;
        $isShort = bccomp($shortage, '0', 6) === 1;
        if (($accepted ? 1 : 0) + ($isDamaged ? 1 : 0) + ($isShort ? 1 : 0) > 1) {
            throw new BusinessRuleException('A tracked roll or scrap can have only one receiving outcome.');
        }
        if (! $accepted && ! $isDamaged && ! $isShort) {
            return;
        }
        $target = $isDamaged ? $damagedWarehouse : $destination;
        if ($item->item_type === 'roll') {
            $roll = InventoryRoll::query()->whereKey($item->roll_id)->where('warehouse_id', $transit->id)->lockForUpdate()->firstOrFail();
            $this->inventory->issueFromSystemTransit($transit, $item->product, '1', 'transfer_out_transit', $reference);
            $this->rollMovements->recordMovement($roll, 'transfer_out_transit', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference);
            if ($isShort) {
                $roll->forceFill(['status' => 'quarantined'])->save();

                return;
            }
            $this->inventory->receive($target, $item->product, '1', $roll->total_cost, $isDamaged ? 'transfer_damaged_in' : 'transfer_in', $reference);
            $roll->forceFill([
                'warehouse_id' => $target->id, 'branch_id' => $target->branch_id,
                'status' => $isDamaged ? 'damaged' : 'available',
            ])->save();
            $this->rollMovements->recordMovement($roll, $isDamaged ? 'damage' : 'transfer_in', $roll->remaining_length, $roll->remaining_length, $roll->remaining_area, $roll->remaining_area, $reference);

            return;
        }
        $scrap = RollScrap::query()->whereKey($item->scrap_id)->where('warehouse_id', $transit->id)->lockForUpdate()->firstOrFail();
        $this->recordScrapMovement($transfer, $item, $scrap, $transit, 'transfer_out_transit');
        if ($isShort) {
            $scrap->forceFill(['status' => 'damaged'])->save();

            return;
        }
        $scrap->forceFill([
            'warehouse_id' => $target->id, 'branch_id' => $target->branch_id,
            'status' => $isDamaged ? 'damaged' : 'available',
        ])->save();
        $this->recordScrapMovement($transfer, $item, $scrap, $target, $isDamaged ? 'transfer_damaged_in' : 'transfer_in');
    }

    private function recordScrapMovement(StockTransfer $transfer, StockTransferItem $item, RollScrap $scrap, Warehouse $warehouse, string $type): void
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
        ]);
    }
}
