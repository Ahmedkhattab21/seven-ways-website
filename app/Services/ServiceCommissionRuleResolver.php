<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCommissionRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ServiceCommissionRuleResolver
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function resolve(
        Service $service,
        ?Branch $branch = null,
        ?Employee $employee = null,
        ?Role $role = null,
        CarbonInterface|string|null $date = null
    ): ?ServiceCommissionRule {
        if ($service->company_id !== $this->tenant->companyId()) {
            return null;
        }
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?: now());

        return ServiceCommissionRule::query()->where('company_id', $service->company_id)
            ->where('service_id', $service->id)->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->where(fn ($q) => $branch ? $q->whereNull('branch_id')->orWhere('branch_id', $branch->id) : $q->whereNull('branch_id'))
            ->where(fn ($q) => $employee ? $q->whereNull('employee_id')->orWhere('employee_id', $employee->id) : $q->whereNull('employee_id'))
            ->where(fn ($q) => $role ? $q->whereNull('role_id')->orWhere('role_id', $role->id) : $q->whereNull('role_id'))
            ->orderByRaw('CASE WHEN employee_id IS NULL THEN 0 ELSE 4 END + CASE WHEN role_id IS NULL THEN 0 ELSE 2 END + CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')->first();
    }
}
