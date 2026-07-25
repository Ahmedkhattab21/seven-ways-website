<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'allocated_at', 'allocated_by', 'reversed_at', 'reversed_by'];

    protected $casts = ['allocated_at' => 'datetime', 'reversed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Allocations are reversed, never deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }
}
