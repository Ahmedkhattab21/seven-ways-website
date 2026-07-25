<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'requested_quantity' => 'decimal:6', 'approved_quantity' => 'decimal:6',
        'prepared_quantity' => 'decimal:6', 'shipped_quantity' => 'decimal:6',
        'received_quantity' => 'decimal:6', 'rejected_quantity' => 'decimal:6',
        'damaged_quantity' => 'decimal:6', 'shortage_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'roll_id');
    }

    public function scrap(): BelongsTo
    {
        return $this->belongsTo(RollScrap::class, 'scrap_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(StockTransferDiscrepancy::class);
    }
}
