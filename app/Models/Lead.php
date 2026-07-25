<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'normalized_phone', 'email', 'vehicle_year',
        'requested_service_text', 'status', 'priority', 'next_follow_up_at', 'lost_reason',
    ];

    protected $casts = ['vehicle_year' => 'integer', 'next_follow_up_at' => 'datetime', 'converted_at' => 'datetime'];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->where('company_id', $user->company_id);
        if (! $user->hasRole('system_admin') && ! $user->isCompanyAdministrator()) {
            $query->whereIn('branch_id', app(\App\Core\Tenancy\TenantContext::class)->accessibleBranches()->pluck('id'));
        }

        return $query;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'vehicle_brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CustomerSource::class, 'source_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class);
    }
}
