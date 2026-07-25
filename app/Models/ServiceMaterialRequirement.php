<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceMaterialRequirement extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_size_id', 'vehicle_type_id', 'product_id', 'unit_id', 'requirement_type',
        'expected_quantity', 'expected_waste_percentage', 'minimum_quantity', 'maximum_quantity',
        'is_required', 'allow_substitution', 'sort_order', 'notes',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:6', 'expected_waste_percentage' => 'decimal:4',
        'minimum_quantity' => 'decimal:6', 'maximum_quantity' => 'decimal:6',
        'is_required' => 'boolean', 'allow_substitution' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function substitutes(): HasMany
    {
        return $this->hasMany(ServiceMaterialSubstitute::class);
    }
}
