<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderService extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'paused_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            $status = WorkOrder::query()->whereKey($line->work_order_id)->value('status');
            if (in_array($status, ['awaiting_quality', 'ready_for_delivery', 'delivered', 'cancelled', 'closed'], true)) {
                throw new BusinessRuleException('Services cannot be added after execution is handed to quality.');
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(WorkOrderServiceTechnician::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderServiceTimeLog::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }
}
