<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityChecklistTemplateItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_required' => 'boolean',
        'is_critical' => 'boolean',
        'requires_photo_on_failure' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistTemplate::class, 'quality_checklist_template_id');
    }
}
