<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            $status = WorkOrder::query()->whereKey($line->work_order_id)->value('status');
            if (in_array($status, ['awaiting_quality', 'ready_for_delivery', 'delivered', 'cancelled', 'closed'], true)) {
                $claimRework = $line->rework_order_id
                    && ReworkOrder::query()->whereKey($line->rework_order_id)->whereNotNull('warranty_claim_id')->exists();
                if (! $claimRework) {
                    throw new BusinessRuleException('Materials cannot be added after execution is handed to quality.');
                }
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function serviceLine(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class, 'work_order_service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class);
    }

    public function reworkOrder(): BelongsTo
    {
        return $this->belongsTo(ReworkOrder::class);
    }
}
