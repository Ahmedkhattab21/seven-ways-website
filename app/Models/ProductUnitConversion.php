<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitConversion extends Model
{
    protected $fillable = ['product_id', 'from_unit_id', 'to_unit_id', 'factor', 'is_purchase_conversion', 'is_sale_conversion'];

    protected $casts = ['factor' => 'decimal:8', 'is_purchase_conversion' => 'boolean', 'is_sale_conversion' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
