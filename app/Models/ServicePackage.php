<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'package_type', 'start_date', 'end_date', 'is_active',
        'requires_warranty', 'default_warranty_film_type', 'default_warranty_duration_value',
        'default_warranty_duration_unit', 'default_warranty_application_area',
        'default_warranty_terms', 'default_warranty_notes',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean',
        'requires_warranty' => 'boolean', 'default_warranty_duration_value' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_package_items')->withPivot(['quantity', 'is_required', 'sort_order']);
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(BranchServicePackage::class);
    }
}
