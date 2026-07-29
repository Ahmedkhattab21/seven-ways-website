<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeManagementService
{
    public function __construct(
        private TenantContext $tenant,
        private EmployeeServiceSkillService $skills,
        private AuditService $audit
    ) {
    }

    public function save(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $before = $employee->exists ? $employee->getOriginal() : [];
            $branch = Branch::query()
                ->where('company_id', $this->tenant->companyId())
                ->where('is_active', true)
                ->findOrFail($data['branch_id']);

            if (! $this->tenant->user()?->canAccessBranch($branch)) {
                throw new BusinessRuleException('الفرع المحدد خارج نطاق صلاحياتك.', status: 403);
            }

            if (! empty($data['user_id'])) {
                $user = User::query()->where('company_id', $this->tenant->companyId())->findOrFail($data['user_id']);
                if (! $user->canAccessBranch($branch)) {
                    throw new BusinessRuleException('حساب المستخدم لا يملك الوصول إلى فرع الموظف.', status: 403);
                }
            }

            $manageSkills = $this->tenant->user()->hasPermission('employees.manage_skills')
                && ((bool) ($data['skills_managed'] ?? false) || array_key_exists('skills', $data));
            $skillRows = $data['skills'] ?? [];
            unset($data['skills'], $data['skills_managed'], $data['return_url']);
            $employee->fill($data)->forceFill(['company_id' => $this->tenant->companyId()])->save();

            if ($manageSkills) {
                $keptServiceIds = [];
                foreach ($skillRows as $row) {
                    $service = Service::query()
                        ->where('company_id', $employee->company_id)
                        ->where('is_active', true)
                        ->findOrFail($row['service_id']);
                    $this->skills->save($employee, $service, collect($row)->except('service_id')->all());
                    $keptServiceIds[] = $service->id;
                }

                $employee->serviceSkills()
                    ->when($keptServiceIds, fn ($query) => $query->whereNotIn('service_id', $keptServiceIds))
                    ->update(['is_active' => false]);
            }

            $this->audit->record($before ? 'employee.updated' : 'employee.created', $employee, [
                'before' => $before,
                'after' => $employee->fresh()->toArray(),
            ]);

            return $employee->fresh(['branch', 'user', 'serviceSkills.service']);
        });
    }

    public function disable(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $employee->update(['status' => 'inactive']);
            $employee->serviceSkills()->update(['is_active' => false]);
            $this->audit->record('employee.disabled', $employee);
        });
    }
}
