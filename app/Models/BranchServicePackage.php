<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchServicePackage extends Model
{
    protected $fillable = ['vehicle_size_id', 'price', 'minimum_price', 'is_available', 'effective_from', 'effective_to'];

    protected $casts = [
        'price' => 'decimal:4', 'minimum_price' => 'decimal:4', 'is_available' => 'boolean',
        'effective_from' => 'date', 'effective_to' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }

    public function vehicleSize(): BelongsTo
    {
        return $this->belongsTo(VehicleSize::class);
    }
}
