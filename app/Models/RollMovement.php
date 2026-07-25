<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollMovement extends BaseModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'length_before' => 'decimal:6', 'length_change' => 'decimal:6', 'length_after' => 'decimal:6',
        'area_before' => 'decimal:6', 'area_change' => 'decimal:6', 'area_after' => 'decimal:6',
        'unit_cost_per_area' => 'decimal:4', 'usable_cost' => 'decimal:4', 'waste_cost' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('Roll movements are append-only.'));
        static::deleting(fn () => throw new BusinessRuleException('Roll movements are append-only.'));
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'inventory_roll_id');
    }
}
