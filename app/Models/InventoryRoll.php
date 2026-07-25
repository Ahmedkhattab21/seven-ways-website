<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryRoll extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id', 'branch_id'];

    protected $casts = [
        'width' => 'decimal:6', 'original_length' => 'decimal:6', 'remaining_length' => 'decimal:6',
        'original_area' => 'decimal:6', 'remaining_area' => 'decimal:6',
        'unit_cost_per_area' => 'decimal:4', 'total_cost' => 'decimal:4',
        'manufacturing_date' => 'date', 'expiry_date' => 'date', 'received_at' => 'datetime',
        'opened_at' => 'datetime', 'finished_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RollMovement::class);
    }

    public function scraps(): HasMany
    {
        return $this->hasMany(RollScrap::class, 'source_roll_id');
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'roll_id');
    }
}
