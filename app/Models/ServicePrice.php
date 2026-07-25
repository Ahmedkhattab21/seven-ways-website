<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePrice extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_size_id', 'vehicle_type_id', 'unit_id', 'price', 'minimum_price',
        'estimated_duration_minutes', 'effective_from', 'effective_to', 'priority', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:4', 'minimum_price' => 'decimal:4', 'effective_from' => 'date',
        'effective_to' => 'date', 'priority' => 'integer', 'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicleSize(): BelongsTo
    {
        return $this->belongsTo(VehicleSize::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
