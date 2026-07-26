<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefund extends BaseModel
{
    use HasFactory, \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = ['id', 'company_id', 'branch_id', 'refund_number', 'status', 'processed_by', 'approved_by', 'cancelled_by'];

    protected $casts = ['refund_date' => 'date', 'approved_at' => 'datetime', 'processed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Refund history cannot be deleted.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SalesCreditNote::class, 'sales_credit_note_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
