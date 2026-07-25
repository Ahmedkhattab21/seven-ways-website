<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryReservation;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryReservationService
{
    public function __construct(private TenantContext $tenant, private InventoryService $inventory, private AuditService $audit)
    {
    }

    public function reserve(
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $referenceType,
        int $referenceId,
        ?string $expiresAt = null,
        ?InventoryRoll $roll = null,
        ?RollScrap $scrap = null
    ): InventoryReservation {
        if (! in_array($referenceType, config('inventory.reservation_reference_types'), true)) {
            throw new BusinessRuleException('Reservation reference type is not allowed.');
        }

        return DB::transaction(function () use ($warehouse, $product, $quantity, $referenceType, $referenceId, $expiresAt, $roll, $scrap) {
            if ($roll) {
                $roll = InventoryRoll::query()->whereKey($roll->id)->lockForUpdate()->firstOrFail();
                if ($roll->warehouse_id !== $warehouse->id || $roll->product_id !== $product->id || ! in_array($roll->status, ['available', 'opened'], true)) {
                    throw new BusinessRuleException('Roll is not available in the source warehouse.');
                }
                $roll->forceFill(['status' => 'reserved', 'updated_by' => $this->tenant->user()->id])->save();
            }
            if ($scrap) {
                $scrap = RollScrap::query()->whereKey($scrap->id)->lockForUpdate()->firstOrFail();
                if ($scrap->warehouse_id !== $warehouse->id || $scrap->sourceRoll?->product_id !== $product->id || $scrap->status !== 'available') {
                    throw new BusinessRuleException('Scrap is not available in the source warehouse.');
                }
                $scrap->forceFill(['status' => 'reserved', 'reserved_at' => now()])->save();
            }
            $movementType = $referenceType === 'stock_transfer' ? 'transfer_reservation' : null;
            $this->inventory->reserve($warehouse, $product, $quantity, false, ['type' => $referenceType, 'id' => $referenceId], $movementType);
            $reservation = new InventoryReservation;
            $reservation->forceFill([
                'uuid' => (string) Str::uuid(), 'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
                'inventory_roll_id' => $roll?->id, 'roll_scrap_id' => $scrap?->id,
                'reference_type' => $referenceType, 'reference_id' => $referenceId, 'quantity' => $quantity,
                'status' => 'active', 'expires_at' => $expiresAt, 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('inventory.reserved', $reservation);

            return $reservation;
        });
    }

    public function release(InventoryReservation $reservation): void
    {
        $this->finish($reservation, 'released');
    }

    public function consume(InventoryReservation $reservation): void
    {
        $this->finish($reservation, 'consumed');
    }

    private function finish(InventoryReservation $reservation, string $status): void
    {
        DB::transaction(function () use ($reservation, $status) {
            $reservation = InventoryReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status !== 'active') {
                throw new BusinessRuleException('Reservation is no longer active.');
            }
            $this->inventory->reserve(
                Warehouse::findOrFail($reservation->warehouse_id),
                Product::findOrFail($reservation->product_id),
                $reservation->quantity,
                true,
                ['type' => $reservation->reference_type, 'id' => $reservation->reference_id],
                $reservation->reference_type === 'stock_transfer' ? 'transfer_reservation_release' : null
            );
            if ($reservation->inventory_roll_id) {
                InventoryRoll::query()->whereKey($reservation->inventory_roll_id)->where('status', 'reserved')
                    ->update(['status' => 'available', 'updated_by' => $this->tenant->user()->id]);
            }
            if ($reservation->roll_scrap_id) {
                RollScrap::query()->whereKey($reservation->roll_scrap_id)->where('status', 'reserved')
                    ->update(['status' => 'available', 'reserved_at' => null]);
            }
            $reservation->forceFill(['status' => $status, 'released_at' => now(), 'released_by' => $this->tenant->user()->id])->save();
            $this->audit->record("inventory.reservation_{$status}", $reservation);
        });
    }
}
