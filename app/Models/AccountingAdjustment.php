<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAdjustment extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['scheduled_reversal_date' => 'date'];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
