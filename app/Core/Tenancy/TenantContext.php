<?php

namespace App\Core\Tenancy;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class TenantContext
{
    private ?User $user = null;

    private ?Branch $branch = null;

    public function initialize(User $user): self
    {
        $this->user = $user;
        $branchId = session('tenant.branch_id');
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        if (! $branch || ! $user->canAccessBranch($branch)) {
            $branch = $this->accessibleBranches()->firstWhere('id', $user->branch_id)
                ?? $this->accessibleBranches()->first();
        }

        $this->branch = $branch;

        if ($branch) {
            session(['tenant.branch_id' => $branch->id]);
        } else {
            session()->forget('tenant.branch_id');
        }

        return $this;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function company(): ?Company
    {
        return $this->user?->company;
    }

    public function companyId(): ?int
    {
        return $this->user?->company_id;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    public function accessibleBranches(): Collection
    {
        if (! $this->user?->company_id) {
            return new Collection();
        }

        $query = Branch::query()
            ->where('company_id', $this->user->company_id)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name');

        if ($this->user->hasRole('branch_manager')
            && ! $this->user->hasRole('system_admin')
            && ! $this->user->isCompanyAdministrator()) {
            $query->whereKey($this->user->branch_id);
        } elseif (! $this->user->hasRole('system_admin') && ! $this->user->isCompanyAdministrator()) {
            $ids = $this->user->accessibleBranches()->wherePivot('can_view', true)->pluck('branches.id');
            if ($this->user->branch_id) {
                $ids->push($this->user->branch_id);
            }
            $query->whereIn('id', $ids->unique());
        }

        return $query->get();
    }

    public function switchTo(Branch $branch): void
    {
        if (! $this->user || ! $this->user->canAccessBranch($branch)) {
            throw new AuthorizationException('ليس لديك صلاحية الوصول لهذا الفرع.');
        }

        $this->branch = $branch;
        session(['tenant.branch_id' => $branch->id]);
    }
}
