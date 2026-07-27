<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCommissionSettlement extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'settlement_number', 'total_amount', 'status',
        'created_by', 'submitted_by', 'approved_by', 'settled_by', 'reversed_by',
        'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = ['settlement_date' => 'date', 'total_amount' => 'decimal:4'];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed commission settlements cannot be deleted.')
            : null);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeCommissionSettlementLine::class, 'commission_settlement_id');
    }
}
