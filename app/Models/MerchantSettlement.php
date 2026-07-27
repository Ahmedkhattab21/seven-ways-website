<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantSettlement extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'document_number', 'gross_amount', 'fees_amount',
        'tax_amount', 'net_amount', 'status', 'idempotency_key', 'created_by', 'submitted_by',
        'approved_by', 'posted_by', 'reversed_by', 'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'settlement_date' => 'date',
        'gross_amount' => 'decimal:4', 'fees_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4', 'net_amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $settlement) {
            if (in_array($settlement->getOriginal('status'), ['posted', 'matched', 'reversed'], true)
                && $settlement->isDirty([
                    'bank_account_id', 'payment_method_id', 'settlement_reference',
                    'period_start', 'period_end', 'settlement_date', 'currency_id',
                    'gross_amount', 'fees_amount', 'tax_amount', 'net_amount',
                ])) {
                throw new BusinessRuleException('Posted merchant settlement data is immutable.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MerchantSettlementLine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
