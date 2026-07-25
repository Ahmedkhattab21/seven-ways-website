<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderReadyToStart;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Throwable;

class WorkOrderMaterialReservationService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations
    ) {
    }

    public function reserve(WorkOrder $order): bool
    {
        $allReserved = true;
        DB::transaction(function () use ($order, &$allReserved) {
            foreach ($order->materials()->where('status', 'planned')->lockForUpdate()->get() as $line) {
                try {
                    $reservation = $this->reservations->reserve(
                        Warehouse::findOrFail($line->warehouse_id),
                        Product::findOrFail($line->product_id),
                        $line->expected_quantity,
                        'work_order',
                        $order->id,
                        null,
                        $line->roll_id ? InventoryRoll::findOrFail($line->roll_id) : null,
                        $line->scrap_id ? RollScrap::findOrFail($line->scrap_id) : null,
                    );
                    $line->forceFill(['reservation_id' => $reservation->id, 'status' => 'reserved'])->save();
                } catch (Throwable $exception) {
                    report($exception);
                    $allReserved = false;
                }
            }
            $from = $order->status;
            $status = $allReserved ? 'ready_to_start' : 'awaiting_materials';
            $order->forceFill(['status' => $status, 'updated_by' => $this->tenant->user()->id])->save();
            if ($from !== $status) {
                $order->statusLogs()->create(['from_status' => $from, 'to_status' => $status, 'changed_by' => $this->tenant->user()->id]);
            }
            if ($allReserved) {
                DB::afterCommit(fn () => event(new WorkOrderReadyToStart($order->id)));
            }
        });

        return $allReserved;
    }

    public function release(WorkOrder $order): void
    {
        $order->materials()->whereNotNull('reservation_id')->where('status', 'reserved')->get()->each(function ($line) {
            $this->reservations->release($line->reservation);
            $line->forceFill(['status' => 'cancelled'])->save();
        });
    }
}
