<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends BaseModel
{
    protected $fillable = ['code', 'name', 'symbol', 'unit_type', 'decimal_places', 'is_active'];

    protected $casts = ['decimal_places' => 'integer', 'is_system' => 'boolean', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function serviceMaterialRequirements(): HasMany
    {
        return $this->hasMany(ServiceMaterialRequirement::class);
    }
}
