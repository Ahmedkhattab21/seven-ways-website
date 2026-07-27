<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBox extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'status', 'created_by', 'updated_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean', 'allows_receipts' => 'boolean', 'allows_payments' => 'boolean',
        'requires_shift_opening' => 'boolean', 'maximum_cash_limit' => 'decimal:4',
        'minimum_cash_limit' => 'decimal:4', 'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Cash boxes are disabled or closed, not deleted.'));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function custodians(): HasMany
    {
        return $this->hasMany(CashBoxCustodian::class);
    }
}
