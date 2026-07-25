<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCreditNote extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'branch_id', 'credit_note_number', 'status', 'subtotal', 'tax_amount', 'total', 'applied_amount', 'refunded_amount', 'remaining_amount', 'created_by', 'approved_by', 'issued_by', 'cancelled_by'];

    protected $casts = ['credit_note_date' => 'date', 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Credit notes cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesCreditNoteItem::class);
    }
}
