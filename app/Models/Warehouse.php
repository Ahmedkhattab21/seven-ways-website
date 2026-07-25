<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'warehouse_type', 'address', 'is_main', 'is_active',
        'is_system', 'allows_sale_issue', 'allows_work_order_issue', 'allows_damaged_stock',
    ];

    protected $casts = [
        'is_main' => 'boolean', 'is_active' => 'boolean', 'is_system' => 'boolean', 'allows_sale_issue' => 'boolean',
        'allows_work_order_issue' => 'boolean', 'allows_damaged_stock' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function rolls(): HasMany
    {
        return $this->hasMany(InventoryRoll::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }
}
