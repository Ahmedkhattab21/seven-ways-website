<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturing_year', 'color', 'plate_number', 'normalized_plate_number',
        'vin', 'odometer', 'status', 'notes',
    ];

    protected $casts = ['manufacturing_year' => 'integer', 'odometer' => 'integer'];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->where('vehicles.company_id', $user->company_id);
        if (! $user->hasRole('system_admin') && ! $user->isCompanyAdministrator()) {
            $branchIds = app(\App\Core\Tenancy\TenantContext::class)->accessibleBranches()->pluck('id');
            $query->whereHas('customer', fn ($customer) => $customer->whereIn('assigned_branch_id', $branchIds));
        }

        return $query;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'created_branch_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'vehicle_brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(VehicleSize::class, 'vehicle_size_id');
    }

    public function ownershipHistory(): HasMany
    {
        return $this->hasMany(VehicleOwnershipHistory::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
