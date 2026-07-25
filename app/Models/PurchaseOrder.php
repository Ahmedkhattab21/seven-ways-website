<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends BaseModel
{
    protected $guarded = [
        'id', 'company_id', 'branch_id', 'purchase_order_number', 'status', 'supplier_name_snapshot',
        'supplier_tax_number_snapshot', 'supplier_address_snapshot', 'subtotal', 'discount_amount',
        'tax_amount', 'rounding_amount', 'total', 'received_amount', 'invoiced_amount', 'created_by',
        'submitted_by', 'approved_by', 'sent_by', 'cancelled_by', 'closed_by',
    ];

    protected $casts = [
        'order_date' => 'date', 'expected_delivery_date' => 'date', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed purchase orders cannot be deleted.')
            : null);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
