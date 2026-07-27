<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementLine extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'bank_statement_import_id', 'bank_account_id', 'line_number',
        'transaction_date', 'value_date', 'bank_reference', 'external_id', 'description', 'debit_amount',
        'credit_amount', 'running_balance', 'currency_id', 'counterparty_name',
        'counterparty_iban_encrypted', 'counterparty_iban_hash', 'counterparty_iban_last4',
        'transaction_code', 'status', 'matched_amount', 'unmatched_amount', 'is_duplicate',
        'duplicate_of_id', 'raw_hash', 'raw_payload', 'ignored_by', 'ignored_at',
    ];

    protected $hidden = ['counterparty_iban_encrypted', 'counterparty_iban_hash', 'raw_payload'];

    protected $casts = [
        'transaction_date' => 'date', 'value_date' => 'date', 'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4', 'running_balance' => 'decimal:4',
        'matched_amount' => 'decimal:4', 'unmatched_amount' => 'decimal:4',
        'is_duplicate' => 'boolean', 'counterparty_iban_encrypted' => 'encrypted',
        'raw_payload' => 'array', 'metadata' => 'array', 'ignored_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Imported statement lines are immutable.'));
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchItems(): HasMany
    {
        return $this->hasMany(BankReconciliationMatchItem::class, 'statement_line_id');
    }

    public function amount(): string
    {
        return bccomp((string) $this->debit_amount, '0', 4) === 1
            ? (string) $this->debit_amount : (string) $this->credit_amount;
    }

    public function direction(): string
    {
        return bccomp((string) $this->debit_amount, '0', 4) === 1 ? 'debit' : 'credit';
    }

    public function maskedCounterpartyIban(): ?string
    {
        return $this->counterparty_iban_last4 ? '••••'.$this->counterparty_iban_last4 : null;
    }
}
