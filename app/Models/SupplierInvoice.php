<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends BaseModel
{
    use HasFactory, \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'internal_invoice_number', 'status', 'subtotal',
        'discount_amount', 'tax_amount', 'rounding_amount', 'total', 'paid_amount',
        'credited_amount', 'balance_due', 'supplier_name_snapshot', 'supplier_tax_number_snapshot',
        'supplier_address_snapshot', 'created_by', 'submitted_by', 'approved_by', 'posted_by', 'cancelled_by',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'due_date' => 'date', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed supplier invoices cannot be deleted.')
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class)->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(SupplierCreditNote::class);
    }
}
