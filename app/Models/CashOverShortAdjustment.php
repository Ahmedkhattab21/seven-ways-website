<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOverShortAdjustment extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'adjustment_type', 'amount', 'status',
        'created_by', 'submitted_by', 'approved_by', 'posted_by', 'reversed_by',
        'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = ['amount' => 'decimal:4'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashBoxSession::class, 'cash_box_session_id');
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(CashBoxCount::class, 'cash_box_count_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }
}
