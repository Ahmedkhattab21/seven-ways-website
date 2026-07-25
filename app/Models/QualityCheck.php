<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class QualityCheck extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'quality_check_number', 'round_number',
        'status', 'checked_by', 'approved_by', 'approved_at', 'completed_at',
        'overall_result', 'requires_rework',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'completed_at' => 'datetime', 'approved_at' => 'datetime',
        'requires_rework' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $check) {
            if (in_array($check->getOriginal('status'), ['passed', 'failed', 'cancelled', 'superseded'], true)) {
                throw new BusinessRuleException('Completed quality checks are immutable.');
            }
        });
        static::deleting(fn () => throw new BusinessRuleException('Quality checks cannot be deleted.'));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistTemplate::class, 'checklist_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QualityCheckItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
