<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'rate', 'tax_type', 'is_default', 'is_inclusive',
        'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:4', 'is_default' => 'boolean', 'is_inclusive' => 'boolean',
        'is_active' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'default_tax_id');
    }
}
