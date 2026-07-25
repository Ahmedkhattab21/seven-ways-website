<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'system_quantity' => 'decimal:6', 'counted_quantity' => 'decimal:6', 'difference_quantity' => 'decimal:6',
        'system_length' => 'decimal:6', 'counted_length' => 'decimal:6', 'difference_length' => 'decimal:6',
        'system_area' => 'decimal:6', 'counted_area' => 'decimal:6', 'difference_area' => 'decimal:6',
        'unit_cost' => 'decimal:4',
    ];

    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
