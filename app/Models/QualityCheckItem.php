<?php

namespace App\Models;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityCheckItem extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'quality_check_id'];

    protected $casts = [
        'is_required' => 'boolean', 'is_critical' => 'boolean', 'photo_required' => 'boolean',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->exists && in_array($item->qualityCheck()->value('status'), ['passed', 'failed', 'cancelled', 'superseded'], true)) {
                throw new BusinessRuleException('Completed quality check items are immutable.');
            }
        });
        static::deleting(fn () => throw new BusinessRuleException('Quality check items cannot be deleted.'));
    }

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class);
    }

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }
}
