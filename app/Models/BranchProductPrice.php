<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProductPrice extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'product_id', 'price', 'minimum_price',
        'effective_from', 'effective_to', 'priority', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'minimum_price' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
