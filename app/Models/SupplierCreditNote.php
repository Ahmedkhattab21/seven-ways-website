<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierCreditNote extends BaseModel
{
    use \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'currency_id', 'credit_note_number', 'status', 'subtotal',
        'tax_amount', 'total', 'applied_amount', 'remaining_amount', 'created_by',
        'approved_by', 'posted_by',
    ];

    protected $casts = ['credit_date' => 'date'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Supplier credit note history cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierCreditNoteItem::class);
    }
}
