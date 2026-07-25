<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends BaseModel
{
    protected $guarded = ['id', 'goods_receipt_id', 'total_cost', 'stock_movement_id'];

    protected $casts = [
        'manufacture_date' => 'date', 'expiry_date' => 'date', 'rolls' => 'array',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdRolls(): HasMany
    {
        return $this->hasMany(InventoryRoll::class);
    }
}
