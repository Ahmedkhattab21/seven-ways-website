<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodAccountMapping extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'payment_method_id', 'account_id', 'bank_account_id', 'cash_box_id',
        'operation_type', 'clearing_account_id', 'fees_account_id', 'settlement_days', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }
}
