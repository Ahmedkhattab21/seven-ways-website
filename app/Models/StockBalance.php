<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:6', 'reserved_quantity' => 'decimal:6',
        'available_quantity' => 'decimal:6', 'average_cost' => 'decimal:4', 'last_movement_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
