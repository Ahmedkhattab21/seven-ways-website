<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityChecklistTemplate extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id', 'version', 'created_by'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(function (self $template) {
            if ($template->qualityChecks()->exists()) {
                throw new BusinessRuleException('Used checklist versions are immutable; create a new version.');
            }
        });
        static::deleting(function (self $template) {
            if ($template->qualityChecks()->exists()) {
                throw new BusinessRuleException('Used checklist versions cannot be deleted.');
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QualityChecklistTemplateItem::class)->orderBy('sort_order');
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class, 'checklist_template_id');
    }
}
