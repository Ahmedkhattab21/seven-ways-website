<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends BaseModel
{
    use \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'payment_number', 'status', 'allocated_amount',
        'unallocated_amount', 'created_by', 'approved_by', 'processed_by', 'cancelled_by',
    ];

    protected $casts = [
        'payment_date' => 'date', 'approved_at' => 'datetime', 'processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Supplier payment history cannot be deleted.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }
}
