<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private TenantContext $tenant,
        private StockMovementService $movements,
        private AuditService $audit,
        private MoneyRoundingService $rounding
    ) {
    }

    public function receive(
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $unitCost,
        string $type,
        array $reference = [],
        ?string $totalCost = null
    ): StockMovement {
        return $this->change($warehouse, $product, $quantity, $unitCost, $type, 'in', $reference, false, false, $totalCost);
    }

    public function issue(
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $type,
        array $reference = [],
        ?callable $insufficientStockMessage = null
    ): StockMovement {
        return $this->change($warehouse, $product, $quantity, null, $type, 'out', $reference, insufficientStockMessage: $insufficientStockMessage);
    }

    public function issueAtCost(Warehouse $warehouse, Product $product, string $quantity, string $unitCost, string $type, array $reference = []): StockMovement
    {
        return $this->change($warehouse, $product, $quantity, $unitCost, $type, 'out', $reference, false, true);
    }

    public function reserve(Warehouse $warehouse, Product $product, string $quantity, bool $release = false, array $reference = [], ?string $movementType = null): StockMovement
    {
        return DB::transaction(function () use ($warehouse, $product, $quantity, $release, $reference, $movementType) {
            $this->assertScope($warehouse, $product);
            $balance = $this->lockedBalance($warehouse, $product);
            if (! $release && bccomp($quantity, $balance->available_quantity, 6) === 1) {
                throw new BusinessRuleException('Reservation exceeds available stock.');
            }
            if ($release && bccomp($quantity, $balance->reserved_quantity, 6) === 1) {
                throw new BusinessRuleException('Release exceeds reserved stock.');
            }
            $balance->reserved_quantity = $release
                ? bcsub($balance->reserved_quantity, $quantity, 6)
                : bcadd($balance->reserved_quantity, $quantity, 6);
            $balance->available_quantity = bcsub($balance->quantity, $balance->reserved_quantity, 6);
            $balance->last_movement_at = now();
            $balance->save();

            return $this->movements->record($this->movementData(
                $warehouse, $product, $quantity, '0', $movementType ?? ($release ? 'reservation_release' : 'reservation'),
                'none', $balance->quantity, $balance->quantity, $reference
            ));
        });
    }

    public function issueFromSystemTransit(Warehouse $warehouse, Product $product, string $quantity, string $type, array $reference): StockMovement
    {
        if (! $warehouse->is_system || $warehouse->warehouse_type !== 'transit'
            || $warehouse->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Only a system transit warehouse can use this operation.', status: 403);
        }

        return $this->change($warehouse, $product, $quantity, null, $type, 'out', $reference, true);
    }

    public function reverse(StockMovement $movement): StockMovement
    {
        if ($movement->reversal_of_id || StockMovement::where('reversal_of_id', $movement->id)->exists()) {
            throw new BusinessRuleException('Movement is already a reversal or has been reversed.');
        }
        $warehouse = Warehouse::findOrFail($movement->warehouse_id);
        $product = Product::findOrFail($movement->product_id);
        $reference = ['type' => 'reversal', 'id' => $movement->id, 'reversal_of_id' => $movement->id];

        $reversal = $movement->direction === 'in'
            ? $this->issue($warehouse, $product, $movement->stock_quantity, 'reversal', $reference)
            : $this->receive($warehouse, $product, $movement->stock_quantity, $movement->unit_cost, 'reversal', $reference);
        $this->audit->record('stock_movement.reversed', $reversal, ['original_id' => $movement->id]);

        return $reversal;
    }

    private function change(
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?string $unitCost,
        string $type,
        string $direction,
        array $reference,
        bool $allowSystemTransit = false,
        bool $preserveProvidedCost = false,
        ?string $providedTotalCost = null,
        ?callable $insufficientStockMessage = null
    ): StockMovement {
        if (bccomp($quantity, '0', 6) <= 0) {
            throw new BusinessRuleException('Stock quantity must be positive.');
        }

        return DB::transaction(function () use ($warehouse, $product, $quantity, $unitCost, $type, $direction, $reference, $allowSystemTransit, $preserveProvidedCost, $providedTotalCost, $insufficientStockMessage) {
            $this->assertScope($warehouse, $product, $allowSystemTransit);
            $balance = $this->lockedBalance($warehouse, $product);
            $before = $balance->quantity;
            if ($direction === 'out') {
                if (! $warehouse->branch?->settings?->allow_negative_stock && bccomp($quantity, $balance->available_quantity, 6) === 1) {
                    throw new BusinessRuleException($insufficientStockMessage
                        ? $insufficientStockMessage((string) $balance->available_quantity)
                        : 'Issue exceeds available stock.');
                }
                $after = bcsub($before, $quantity, 6);
                $unitCost = $preserveProvidedCost ? $unitCost : $balance->average_cost;
            } else {
                $after = bcadd($before, $quantity, 6);
                if ($product->costing_method === 'weighted_average') {
                    $oldValue = bcmul($before, $balance->average_cost, 8);
                    $newValue = $providedTotalCost ?? bcmul($quantity, $unitCost ?? '0', 8);
                    $balance->average_cost = bccomp($after, '0', 6) === 0
                        ? '0.0000'
                        : $this->rounding->round(bcdiv(bcadd($oldValue, $newValue, 8), $after, 8), 4);
                } elseif ($product->costing_method === 'standard') {
                    $balance->average_cost = $product->standard_cost ?? '0';
                }
            }
            $balance->quantity = $after;
            $balance->available_quantity = bcsub($after, $balance->reserved_quantity, 6);
            $balance->last_movement_at = now();
            $balance->save();

            return $this->movements->record($this->movementData(
                $warehouse,
                $product,
                $quantity,
                $unitCost ?? '0',
                $type,
                $direction,
                $before,
                $after,
                $reference,
                $providedTotalCost
            ));
        });
    }

    private function lockedBalance(Warehouse $warehouse, Product $product): StockBalance
    {
        StockBalance::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
            ['company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id]
        );

        return StockBalance::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
    }

    private function assertScope(Warehouse $warehouse, Product $product, bool $allowSystemTransit = false): void
    {
        if ($warehouse->company_id !== $this->tenant->companyId() || $product->company_id !== $this->tenant->companyId()
            || (! $this->tenant->accessibleBranches()->contains('id', $warehouse->branch_id)
                && ! ($allowSystemTransit && $warehouse->is_system && $warehouse->warehouse_type === 'transit'))) {
            throw new BusinessRuleException('Inventory record is outside the current tenant.', status: 403);
        }
    }

    private function movementData(
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $unitCost,
        string $type,
        string $direction,
        string $before,
        string $after,
        array $reference,
        ?string $providedTotalCost = null
    ): array {
        return [
            'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'movement_type' => $type, 'direction' => $direction,
            'reference_type' => $reference['type'] ?? null, 'reference_id' => $reference['id'] ?? null,
            'quantity' => $quantity, 'unit_id' => $product->stock_unit_id, 'stock_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $providedTotalCost ?? bcmul($quantity, $unitCost, 4),
            'balance_before' => $before, 'balance_after' => $after,
            'reversal_of_id' => $reference['reversal_of_id'] ?? null, 'notes' => $reference['notes'] ?? null,
        ];
    }
}
