<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBoxCustodian extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'assigned_by', 'revoked_by', 'revoked_at'];

    protected $casts = [
        'valid_from' => 'date', 'valid_to' => 'date', 'can_receive' => 'boolean',
        'can_pay' => 'boolean', 'can_transfer' => 'boolean', 'payment_limit' => 'decimal:4',
        'is_primary' => 'boolean', 'is_active' => 'boolean', 'revoked_at' => 'datetime',
    ];

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
