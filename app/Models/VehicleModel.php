<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleModel extends BaseModel
{
    protected $fillable = [
        'vehicle_brand_id', 'name_ar', 'name_en', 'start_year', 'end_year', 'is_active',
    ];

    protected $casts = ['start_year' => 'integer', 'end_year' => 'integer', 'is_active' => 'boolean'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'vehicle_brand_id');
    }
}
