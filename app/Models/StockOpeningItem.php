<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpeningItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'decimal:6', 'unit_cost' => 'decimal:4', 'roll_width' => 'decimal:6', 'roll_length' => 'decimal:6'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockOpeningDocument::class, 'stock_opening_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
