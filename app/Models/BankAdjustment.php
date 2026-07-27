<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAdjustment extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'document_number', 'status', 'created_by', 'submitted_by',
        'approved_by', 'posted_by', 'reversed_by', 'submitted_at', 'approved_at', 'posted_at',
        'reversed_at', 'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = [
        'adjustment_date' => 'date', 'exchange_rate' => 'decimal:8', 'amount' => 'decimal:4',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Bank adjustments are cancelled or reversed, not deleted.'));
        static::updating(function (self $adjustment) {
            if (in_array($adjustment->getOriginal('status'), ['posted', 'reversed'], true)
                && $adjustment->isDirty(['bank_account_id', 'adjustment_type', 'adjustment_date', 'amount', 'offset_account_id'])) {
                throw new BusinessRuleException('Posted bank adjustment is immutable.');
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationSession::class, 'bank_reconciliation_session_id');
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
