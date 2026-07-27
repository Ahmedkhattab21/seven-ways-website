<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExpenseClaimItem extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'expense_claim_id', 'tax_rate', 'tax_amount', 'total_amount'];

    protected $casts = [
        'expense_date' => 'date', 'net_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:4', 'total_amount' => 'decimal:4',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(EmployeeExpenseClaim::class, 'expense_claim_id');
    }
}
