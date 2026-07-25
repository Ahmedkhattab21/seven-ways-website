<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRollConsumptionProfile extends Model
{
    protected $fillable = [
        'service_id', 'vehicle_size_id', 'vehicle_type_id', 'film_product_id', 'coverage_type',
        'expected_width', 'expected_length', 'expected_area', 'expected_waste_percentage',
        'minimum_scrap_width', 'minimum_scrap_length', 'notes',
    ];

    protected $casts = [
        'expected_width' => 'decimal:4', 'expected_length' => 'decimal:4',
        'expected_area' => 'decimal:6', 'expected_waste_percentage' => 'decimal:4',
        'minimum_scrap_width' => 'decimal:4', 'minimum_scrap_length' => 'decimal:4',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function filmProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'film_product_id');
    }
}
