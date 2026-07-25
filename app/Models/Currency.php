<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'symbol', 'decimal_places', 'is_active'];

    protected $casts = ['decimal_places' => 'integer', 'is_active' => 'boolean'];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
