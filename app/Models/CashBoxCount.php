<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashBoxCount extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'status', 'counted_total', 'book_total', 'difference',
        'counted_by', 'reviewed_by', 'approved_by', 'counted_at', 'reviewed_at', 'approved_at',
    ];

    protected $casts = [
        'counted_total' => 'decimal:4', 'book_total' => 'decimal:4', 'difference' => 'decimal:4',
        'counted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $count) {
            if ($count->getOriginal('status') === 'approved') {
                throw new BusinessRuleException('Approved cash count is immutable.');
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashBoxSession::class, 'cash_box_session_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashBoxCountLine::class);
    }
}
