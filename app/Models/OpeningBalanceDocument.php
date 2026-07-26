<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningBalanceDocument extends BaseModel
{
    use HasFactory, \App\Models\Concerns\HasAccountingPosting;

    protected $fillable = ['branch_id', 'fiscal_year_id', 'balance_date', 'description'];

    protected $casts = [
        'balance_date' => 'date', 'total_debit' => 'decimal:4', 'total_credit' => 'decimal:4',
        'posted_at' => 'datetime', 'reversed_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningBalanceLine::class)->orderBy('sort_order');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
