<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GoodsReceipt extends BaseModel
{
    protected $guarded = [
        'id', 'company_id', 'branch_id', 'goods_receipt_number', 'status', 'received_by',
        'inspected_by', 'posted_by', 'cancelled_by',
    ];

    protected $casts = [
        'receipt_date' => 'date', 'received_at' => 'datetime', 'inspected_at' => 'datetime',
        'posted_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed receipts cannot be deleted.')
            : null);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
