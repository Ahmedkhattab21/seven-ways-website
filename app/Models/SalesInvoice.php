<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends BaseModel
{
    use HasFactory, \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = ['id', 'company_id', 'branch_id', 'invoice_number', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'rounding_amount', 'total', 'paid_amount', 'credited_amount', 'refunded_amount', 'balance_due', 'created_by', 'submitted_by', 'approved_by', 'issued_by', 'cancelled_by', 'voided_by'];

    protected $casts = [
        'invoice_date' => 'date', 'due_date' => 'date', 'price_includes_tax' => 'boolean',
        'vehicle_snapshot' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime',
        'issued_at' => 'datetime', 'cancelled_at' => 'datetime', 'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $invoice) {
            if ($invoice->status !== 'draft') {
                throw new BusinessRuleException('Issued or processed invoices cannot be deleted.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(SalesCreditNote::class);
    }
}
