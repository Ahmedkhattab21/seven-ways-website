<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliationSession extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'session_number', 'statement_opening_balance',
        'statement_closing_balance', 'book_opening_balance', 'book_closing_balance',
        'matched_statement_amount', 'matched_book_amount', 'unreconciled_statement_amount',
        'unreconciled_book_amount', 'difference', 'status', 'started_by', 'reviewed_by',
        'approved_by', 'completed_by', 'reopened_by', 'started_at', 'reviewed_at',
        'approved_at', 'completed_at', 'reopened_at',
    ];

    protected $casts = [
        'date_from' => 'date', 'date_to' => 'date', 'statement_opening_balance' => 'decimal:4',
        'statement_closing_balance' => 'decimal:4', 'book_opening_balance' => 'decimal:4',
        'book_closing_balance' => 'decimal:4', 'matched_statement_amount' => 'decimal:4',
        'matched_book_amount' => 'decimal:4', 'unreconciled_statement_amount' => 'decimal:4',
        'unreconciled_book_amount' => 'decimal:4', 'difference' => 'decimal:4',
        'tolerance' => 'decimal:4', 'started_at' => 'datetime', 'reviewed_at' => 'datetime',
        'approved_at' => 'datetime', 'completed_at' => 'datetime', 'reopened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Reconciliation sessions are permanent records.'));
        static::updating(function (self $session) {
            if ($session->getOriginal('status') === 'completed' && $session->status !== 'reopened') {
                throw new BusinessRuleException('Completed reconciliation is immutable.');
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function imports(): BelongsToMany
    {
        return $this->belongsToMany(
            BankStatementImport::class,
            'bank_reconciliation_session_imports',
            'bank_reconciliation_session_id',
            'bank_statement_import_id'
        )->withPivot('company_id', 'created_at');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BankAdjustment::class);
    }
}
