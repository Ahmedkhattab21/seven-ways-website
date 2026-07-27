<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountBranchAccess extends Model
{
    protected $table = 'bank_account_branch_access';

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'can_view' => 'boolean', 'can_receive' => 'boolean', 'can_pay' => 'boolean',
        'can_transfer' => 'boolean', 'is_active' => 'boolean',
        'daily_payment_limit' => 'decimal:4', 'daily_transfer_limit' => 'decimal:4',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
