<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_category_id', 'code', 'name', 'short_description', 'description', 'service_type',
        'pricing_type', 'default_duration_minutes', 'default_tax_id', 'pricing_unit_id',
        'default_warranty_months', 'requires_vehicle', 'requires_inspection', 'requires_quality_check',
        'allows_multiple_technicians', 'is_package_only', 'is_active',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer', 'default_warranty_months' => 'integer',
        'requires_vehicle' => 'boolean', 'requires_inspection' => 'boolean',
        'requires_quality_check' => 'boolean', 'allows_multiple_technicians' => 'boolean',
        'is_package_only' => 'boolean', 'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    public function pricingUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'pricing_unit_id');
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(ServiceMaterialRequirement::class);
    }

    public function rollProfiles(): HasMany
    {
        return $this->hasMany(ServiceRollConsumptionProfile::class);
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeServiceSkill::class);
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(ServiceCommissionRule::class);
    }
}
