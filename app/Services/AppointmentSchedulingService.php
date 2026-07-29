<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchServicePackage;
use App\Models\Employee;
use App\Models\EmployeeServiceSkill;
use App\Models\Service;
use Illuminate\Support\Carbon;

class AppointmentSchedulingService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function validate(
        Branch $branch,
        Carbon $start,
        Carbon $end,
        ?Employee $employee,
        array $serviceIds,
        ?Appointment $ignore = null,
        array $packageServiceIds = []
    ): void
    {
        if ($branch->company_id !== $this->tenant->companyId() || ! $branch->is_active
            || ! $this->tenant->user()?->canAccessBranch($branch) || $end->lte($start)) {
            throw new BusinessRuleException('Invalid branch or appointment period.', status: 403);
        }
        $settings = $branch->settings;
        if ($settings) {
            $weekendDays = array_map('intval', $settings->weekend_days ?? []);
            if (in_array($start->dayOfWeek, $weekendDays, true)) {
                throw new BusinessRuleException('The branch is closed on the selected day.');
            }
            if ($settings->working_day_start && $start->format('H:i:s') < $settings->working_day_start
                || $settings->working_day_end && $end->format('H:i:s') > $settings->working_day_end) {
                throw new BusinessRuleException('Appointment is outside branch working hours.');
            }
        }
        if ($employee) {
            if ($employee->company_id !== $branch->company_id || $employee->branch_id !== $branch->id || $employee->status !== 'active') {
                throw new BusinessRuleException('Assigned employee is outside the branch.');
            }
            $overlap = Appointment::query()->where('assigned_employee_id', $employee->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
                ->where('scheduled_start', '<', $end)->where('scheduled_end', '>', $start)->exists();
            if ($overlap) {
                throw new BusinessRuleException('The technician has an overlapping appointment.');
            }
            foreach (array_unique($serviceIds) as $serviceId) {
                $hasSkills = EmployeeServiceSkill::query()->where('service_id', $serviceId)->where('is_active', true)->exists();
                if ($hasSkills && ! EmployeeServiceSkill::query()->where('service_id', $serviceId)
                    ->where('employee_id', $employee->id)->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('certification_expires_at')
                        ->orWhereDate('certification_expires_at', '>=', $start))->exists()) {
                    throw new BusinessRuleException('The assigned technician lacks an active required service skill.');
                }
            }
        }
        foreach (array_unique($serviceIds) as $serviceId) {
            $service = Service::query()->whereKey($serviceId)
                ->where('company_id', $branch->company_id)->where('is_active', true)->firstOrFail();
            $availability = \App\Models\BranchService::query()->where('branch_id', $branch->id)
                ->where('service_id', $serviceId)->where('is_available', true)->where('is_active', true)->first();
            $availableThroughPackage = ! $availability && $this->availableThroughPackage(
                $branch,
                $serviceId,
                $packageServiceIds[$serviceId] ?? []
            );
            if (! $availability && ! $availableThroughPackage) {
                throw new BusinessRuleException("الخدمة «{$service->name}» غير متاحة في الفرع «{$branch->name}».");
            }
            if ($availability?->minimum_notice_minutes && now()->addMinutes($availability->minimum_notice_minutes)->gt($start)) {
                throw new BusinessRuleException('The service minimum notice period is not satisfied.');
            }
            if ($availability?->maximum_daily_capacity) {
                $count = \App\Models\AppointmentService::query()->where('service_id', $serviceId)
                    ->whereHas('appointment', fn ($query) => $query->where('branch_id', $branch->id)
                        ->whereDate('scheduled_start', $start->toDateString())
                        ->whereNotIn('status', ['cancelled', 'no_show']))
                    ->when($ignore, fn ($query) => $query->where('appointment_id', '!=', $ignore->id))->count();
                if ($count >= $availability->maximum_daily_capacity) {
                    throw new BusinessRuleException('The service daily capacity has been reached.');
                }
            }
        }
    }

    private function availableThroughPackage(Branch $branch, int $serviceId, array $packageIds): bool
    {
        if ($packageIds === []) {
            return false;
        }

        return BranchServicePackage::query()
            ->where('branch_id', $branch->id)
            ->whereIn('service_package_id', $packageIds)
            ->where('is_available', true)
            ->whereHas('package', fn ($query) => $query
                ->where('company_id', $branch->company_id)
                ->where('is_active', true)
                ->whereHas('items', fn ($query) => $query->where('service_id', $serviceId)))
            ->exists();
    }
}
