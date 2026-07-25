<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'branch_id', 'payment_number', 'status', 'allocated_amount', 'unallocated_amount', 'received_by', 'approved_by', 'cancelled_by'];

    protected $casts = ['payment_date' => 'date', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Payment history cannot be deleted.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function appointmentDeposit(): BelongsTo
    {
        return $this->belongsTo(AppointmentDeposit::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
