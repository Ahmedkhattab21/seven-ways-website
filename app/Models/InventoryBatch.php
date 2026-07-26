<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends BaseModel
{
    protected $guarded = [
        'id', 'company_id', 'received_quantity', 'available_quantity', 'total_cost', 'unit_cost', 'status',
    ];

    protected $casts = ['manufacture_date' => 'date', 'expiry_date' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
