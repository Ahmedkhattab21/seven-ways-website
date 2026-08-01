<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProduct extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'product_id', 'default_sales_warehouse_id',
        'is_available', 'is_sellable', 'minimum_stock', 'maximum_stock',
        'reorder_quantity', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'is_sellable' => 'boolean',
        'minimum_stock' => 'decimal:6',
        'maximum_stock' => 'decimal:6',
        'reorder_quantity' => 'decimal:6',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function defaultSalesWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_sales_warehouse_id');
    }
}
