<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPostingLink extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Accounting posting links are permanent.'));
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
