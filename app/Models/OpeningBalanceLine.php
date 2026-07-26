<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id', 'branch_id', 'cost_center_id', 'currency_id', 'exchange_rate',
        'debit_amount', 'credit_amount', 'customer_id', 'supplier_id', 'employee_id',
        'vehicle_id', 'description', 'sort_order',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:8', 'debit_amount' => 'decimal:4', 'credit_amount' => 'decimal:4',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceDocument::class, 'opening_balance_document_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
