<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpeningDocument extends BaseModel
{
    protected $guarded = ['id', 'company_id', 'branch_id'];

    protected $casts = ['opening_date' => 'date', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpeningItem::class);
    }
}
