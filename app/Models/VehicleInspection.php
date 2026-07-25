<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleInspection extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'customer_approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_items' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(VehicleInspectionItem::class, 'inspection_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
