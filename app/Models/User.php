<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\Support\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function responsibleBranch(): HasOne
    {
        return $this->hasOne(Branch::class, 'responsible_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function accessibleBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access')
            ->withPivot(['is_default', 'can_view', 'can_create', 'can_update', 'can_approve'])
            ->withTimestamps();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function hasRole(string|array $roles): bool
    {
        return $this->roles()->whereIn('name', (array) $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function isCompanyAdministrator(): bool
    {
        return $this->hasRole(['company_owner', 'general_manager']);
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($branch->company_id !== $this->company_id || ! $branch->is_active) {
            return false;
        }

        if ($this->hasRole('branch_manager') && ! $this->isCompanyAdministrator() && ! $this->hasRole('system_admin')) {
            return $this->branch_id === $branch->id;
        }

        return $this->hasRole('system_admin')
            || $this->isCompanyAdministrator()
            || $this->branch_id === $branch->id
            || $this->accessibleBranches()->whereKey($branch->id)->wherePivot('can_view', true)->exists();
    }
}
