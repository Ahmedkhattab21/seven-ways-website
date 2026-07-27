<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cheque extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'cheque_scope_key', 'document_number', 'status',
        'source_type', 'source_id', 'journal_entry_id', 'clearance_journal_entry_id',
        'bounce_journal_entry_id', 'reversal_journal_entry_id', 'created_by', 'submitted_by',
        'approved_by', 'deposited_by', 'cleared_by', 'bounced_by', 'cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4', 'issue_date' => 'date', 'due_date' => 'date',
        'received_date' => 'date', 'deposit_date' => 'date', 'clearance_date' => 'date',
        'bounce_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $cheque) {
            if (in_array($cheque->getOriginal('status'), [
                'deposited', 'under_collection', 'presented', 'cleared', 'bounced',
                'returned', 'cancelled', 'replaced',
            ], true) && $cheque->isDirty([
                'direction', 'cheque_number', 'bank_id', 'bank_account_id', 'currency_id',
                'amount', 'issue_date', 'due_date', 'clearing_account_id', 'offset_account_id',
            ])) {
                throw new BusinessRuleException('Core cheque data is immutable after deposit or presentation.');
            }
        });
        static::deleting(fn () => throw new BusinessRuleException('Cheque history cannot be deleted.'));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChequeStatusHistory::class)->orderBy('id');
    }

    public function endorsements(): HasMany
    {
        return $this->hasMany(ChequeEndorsement::class)->orderBy('id');
    }

    public function maskedNumber(): string
    {
        $visible = substr($this->cheque_number, -4);

        return str_repeat('*', max(strlen($this->cheque_number) - 4, 4)).$visible;
    }
}
