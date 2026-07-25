<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ReworkOrder extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'rework_number', 'status', 'approved_by',
        'additional_material_cost', 'additional_waste_cost', 'additional_labor_cost',
        'total_rework_cost', 'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'completed_at' => 'datetime',
        'is_customer_chargeable' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Rework history cannot be deleted.'));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class);
    }

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ReworkOrderService::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
