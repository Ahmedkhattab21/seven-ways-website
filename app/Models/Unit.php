<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends BaseModel
{
    protected $fillable = ['code', 'name', 'symbol', 'unit_type', 'decimal_places', 'is_active'];

    protected $casts = ['decimal_places' => 'integer', 'is_system' => 'boolean', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
