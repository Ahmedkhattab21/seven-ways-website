<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'status', 'iban_hash', 'created_by', 'updated_by',
        'closed_by', 'closed_at', 'last_reconciled_date',
    ];

    protected $hidden = ['iban', 'iban_hash'];

    protected $casts = [
        'iban' => 'encrypted', 'opening_date' => 'date', 'closing_date' => 'date',
        'last_reconciled_date' => 'date', 'closed_at' => 'datetime',
        'is_primary' => 'boolean', 'allows_receipts' => 'boolean', 'allows_payments' => 'boolean',
        'allows_transfers' => 'boolean', 'requires_reconciliation' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Bank accounts are disabled or closed, not deleted.'));
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
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

    public function branchAccess(): HasMany
    {
        return $this->hasMany(BankAccountBranchAccess::class);
    }

    public function statementImports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    public function reconciliationSessions(): HasMany
    {
        return $this->hasMany(BankReconciliationSession::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BankAdjustment::class);
    }

    public function maskedIban(): ?string
    {
        $iban = preg_replace('/\s+/', '', (string) $this->iban);

        return $iban === '' ? null : str_repeat('•', max(strlen($iban) - 4, 4)).substr($iban, -4);
    }
}
