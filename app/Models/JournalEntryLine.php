<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid', 'journal_entry_id'];

    protected $casts = [
        'exchange_rate' => 'decimal:8', 'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4', 'base_debit_amount' => 'decimal:4',
        'base_credit_amount' => 'decimal:4', 'metadata' => 'array',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
