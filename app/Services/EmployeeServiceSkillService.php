<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BranchService;
use App\Models\Employee;
use App\Models\EmployeeServiceSkill;
use App\Models\Service;

class EmployeeServiceSkillService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(Employee $employee, Service $service, array $data): EmployeeServiceSkill
    {
        if ($employee->company_id !== $this->tenant->companyId() || $service->company_id !== $employee->company_id
            || ! $this->tenant->user()?->canAccessBranch($employee->branch)) {
            throw new BusinessRuleException('Employee, service, or branch is outside your scope.', status: 403);
        }
        $available = BranchService::query()
            ->where('company_id', $employee->company_id)
            ->where('branch_id', $employee->branch_id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->exists();
        if (! $available) {
            throw new BusinessRuleException('الخدمة غير متاحة في فرع الموظف.');
        }

        $skill = EmployeeServiceSkill::query()->firstOrNew(['employee_id' => $employee->id, 'service_id' => $service->id]);
        $skill->fill($data)->forceFill([
            'company_id' => $employee->company_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ])->save();
        $this->audit->record('employee_service_skill.saved', $skill, ['employee_id' => $employee->id, 'service_id' => $service->id]);

        return $skill;
    }
}
