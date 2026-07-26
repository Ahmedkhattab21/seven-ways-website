<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends BaseModel
{
    use HasFactory;

    protected $fillable = ['branch_id', 'entry_date', 'description', 'reference', 'is_adjusting'];

    protected $casts = [
        'entry_date' => 'date', 'posting_date' => 'date', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'posted_at' => 'datetime', 'reversed_at' => 'datetime',
        'cancelled_at' => 'datetime', 'is_automatic' => 'boolean', 'is_reversal' => 'boolean',
        'is_opening' => 'boolean', 'is_adjusting' => 'boolean', 'exchange_rate' => 'decimal:8',
        'total_debit' => 'decimal:4', 'total_credit' => 'decimal:4',
        'base_total_debit' => 'decimal:4', 'base_total_credit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $entry) {
            if ($entry->status !== 'draft') {
                throw new BusinessRuleException('Only draft journal entries can be deleted.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
