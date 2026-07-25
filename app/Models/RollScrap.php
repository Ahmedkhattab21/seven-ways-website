<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RollScrap extends BaseModel
{
    use SoftDeletes;

    protected $guarded = ['id', 'company_id', 'branch_id'];

    protected $casts = [
        'width' => 'decimal:6', 'length' => 'decimal:6', 'area' => 'decimal:6',
        'unit_cost_per_area' => 'decimal:4', 'total_cost' => 'decimal:4',
        'reserved_at' => 'datetime', 'consumed_at' => 'datetime',
    ];

    public function sourceRoll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'source_roll_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transferItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'scrap_id');
    }
}
