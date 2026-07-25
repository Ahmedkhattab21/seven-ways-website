<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleType extends BaseModel
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_system' => 'boolean', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
