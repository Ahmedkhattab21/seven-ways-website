<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleBrand extends BaseModel
{
    protected $fillable = ['name_ar', 'name_en', 'country_code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }
}
