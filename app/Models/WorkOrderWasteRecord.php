<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderWasteRecord extends BaseModel
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('Waste records are append-only.'));
        static::deleting(fn () => throw new BusinessRuleException('Waste records are append-only.'));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(WorkOrderMaterial::class, 'work_order_material_id');
    }
}
