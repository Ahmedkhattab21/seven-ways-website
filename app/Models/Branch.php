<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'responsible_user_id', 'responsible_assigned_at', 'code', 'name', 'commercial_name', 'email', 'phone',
        'tax_number', 'address', 'latitude', 'longitude', 'is_main', 'is_active',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'responsible_assigned_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function accessibleUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch_access')
            ->withPivot(['is_default', 'can_view', 'can_create', 'can_update', 'can_approve'])
            ->withTimestamps();
    }

    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_branch_id');
    }

    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_branch_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function outgoingStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_branch_id');
    }

    public function incomingStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_branch_id');
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function employeeServiceSkills(): HasMany
    {
        return $this->hasMany(EmployeeServiceSkill::class);
    }
}
