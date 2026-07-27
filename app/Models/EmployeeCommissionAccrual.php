<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCommissionAccrual extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'branch_id', 'source_key', 'basis_amount',
        'rule_value', 'commission_amount', 'settled_amount', 'calculation_snapshot',
        'status', 'created_by', 'submitted_by', 'approved_by', 'reversed_by',
        'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = [
        'accrual_date' => 'date', 'basis_amount' => 'decimal:4',
        'rule_value' => 'decimal:4', 'commission_amount' => 'decimal:4',
        'settled_amount' => 'decimal:4', 'calculation_snapshot' => 'array',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Commission accrual history cannot be deleted.'));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EmployeeCommissionRule::class, 'commission_rule_id');
    }

    public function settlementLines(): HasMany
    {
        return $this->hasMany(EmployeeCommissionSettlementLine::class, 'commission_accrual_id');
    }
}
