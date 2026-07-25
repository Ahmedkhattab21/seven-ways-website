<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_type', 'name', 'company_name', 'phone', 'normalized_phone',
        'alternative_phone', 'email', 'tax_number', 'commercial_registration',
        'preferred_language', 'credit_limit', 'payment_term_days', 'status',
        'source_id', 'last_contact_at',
    ];

    protected $casts = ['credit_limit' => 'decimal:4', 'last_contact_at' => 'datetime'];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->where('company_id', $user->company_id);
        if (! $user->hasRole('system_admin') && ! $user->isCompanyAdministrator()) {
            $branchIds = app(\App\Core\Tenancy\TenantContext::class)->accessibleBranches()->pluck('id');
            $query->whereIn('assigned_branch_id', $branchIds);
        }

        return $query;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'created_branch_id');
    }

    public function assignedBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'assigned_branch_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CustomerSource::class, 'source_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
