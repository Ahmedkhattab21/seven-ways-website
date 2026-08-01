<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'promotion_type', 'discount_type', 'discount_value',
        'start_at', 'end_at', 'usage_limit', 'per_customer_limit', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:4', 'start_at' => 'datetime', 'end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'promotion_services');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ServicePackage::class, 'promotion_packages');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'promotion_branches');
    }
}
