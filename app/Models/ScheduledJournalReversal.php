<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledJournalReversal extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['scheduled_date' => 'date', 'processed_at' => 'datetime'];

    public function originalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'original_journal_entry_id');
    }
}
