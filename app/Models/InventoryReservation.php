<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends BaseModel
{
    protected $guarded = ['id', 'company_id', 'branch_id'];

    protected $casts = [
        'quantity' => 'decimal:6', 'length' => 'decimal:6', 'area' => 'decimal:6',
        'expires_at' => 'datetime', 'released_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'inventory_roll_id');
    }

    public function scrap(): BelongsTo
    {
        return $this->belongsTo(RollScrap::class, 'roll_scrap_id');
    }
}
