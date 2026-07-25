<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $guarded = ['id', 'supplier_id'];

    protected $casts = [
        'conversion_factor' => 'decimal:6', 'last_purchase_price' => 'decimal:4',
        'default_purchase_price' => 'decimal:4', 'minimum_order_quantity' => 'decimal:6',
        'is_preferred' => 'boolean', 'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }
}
