<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'advance_number', 'settled_amount', 'status',
        'created_by', 'submitted_by', 'approved_by', 'disbursed_by', 'closed_by',
        'reversed_by', 'cash_payment_id', 'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = [
        'request_date' => 'date', 'amount' => 'decimal:4', 'settled_amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $advance) => $advance->status !== 'draft'
            ? throw new BusinessRuleException('Processed employee advances cannot be deleted.')
            : null);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceSettlement::class);
    }
}
