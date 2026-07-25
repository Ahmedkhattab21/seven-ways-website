<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMaterialSubstitute extends Model
{
    protected $fillable = [
        'service_material_requirement_id', 'substitute_product_id', 'priority', 'conversion_factor', 'is_active',
    ];

    protected $casts = ['conversion_factor' => 'decimal:6', 'is_active' => 'boolean'];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ServiceMaterialRequirement::class, 'service_material_requirement_id');
    }

    public function substituteProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'substitute_product_id');
    }
}
