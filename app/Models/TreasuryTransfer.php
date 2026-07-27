<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransfer extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'document_number', 'status', 'journal_entry_id',
        'created_by', 'submitted_by', 'approved_by', 'completed_by', 'cancelled_by',
        'processed_by', 'reversed_by', 'reversal_journal_entry_id', 'idempotency_key',
        'submitted_at', 'approved_at', 'processed_at', 'failed_at', 'failure_reason',
        'completed_at', 'reversed_at', 'cancelled_at',
    ];

    protected $casts = [
        'transfer_date' => 'date', 'exchange_rate' => 'decimal:8', 'amount' => 'decimal:4',
        'fees_amount' => 'decimal:4', 'submitted_at' => 'datetime', 'approved_at' => 'datetime',
        'processed_at' => 'datetime', 'failed_at' => 'datetime', 'completed_at' => 'datetime',
        'reversed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $transfer) {
            if (in_array($transfer->getOriginal('status'), ['completed', 'reversed'], true)
                && $transfer->isDirty([
                    'from_type', 'from_bank_account_id', 'from_cash_box_id', 'to_type',
                    'to_bank_account_id', 'to_cash_box_id', 'branch_id', 'destination_branch_id',
                    'currency_id', 'exchange_rate', 'amount', 'fees_amount', 'transfer_date',
                ])) {
                throw new BusinessRuleException('Completed treasury transfer data is immutable.');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
