<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee
            ? $this->user()->can('update', $employee)
            : $this->user()->hasPermission('employees.create');
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $employee = $this->route('employee');

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId))
                    ->ignore($employee),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'temporary', 'intern'])],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'skills' => [Rule::prohibitedIf(! $this->user()->hasPermission('employees.manage_skills')), 'nullable', 'array'],
            'skills_managed' => [Rule::prohibitedIf(! $this->user()->hasPermission('employees.manage_skills')), 'nullable', 'boolean'],
            'skills.*.service_id' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'skills.*.skill_level' => ['required', Rule::in(['trainee', 'junior', 'intermediate', 'senior', 'expert'])],
            'skills.*.is_primary' => ['required', 'boolean'],
            'skills.*.is_active' => ['required', 'boolean'],
            'skills.*.certified_at' => ['nullable', 'date'],
            'skills.*.certification_expires_at' => ['nullable', 'date', 'after_or_equal:skills.*.certified_at'],
            'skills.*.notes' => ['nullable', 'string', 'max:2000'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = Branch::query()->find($this->integer('branch_id'));
            if (! $branch || ! $this->user()->canAccessBranch($branch)) {
                $validator->errors()->add('branch_id', 'الفرع المحدد خارج نطاق صلاحياتك.');

                return;
            }

            if ($this->filled('user_id')) {
                $linkedUser = User::query()->find($this->integer('user_id'));
                if (! $linkedUser || ! $linkedUser->canAccessBranch($branch)) {
                    $validator->errors()->add('user_id', 'حساب المستخدم لا يملك الوصول إلى فرع الموظف.');
                }

                $employee = $this->route('employee');
                $alreadyLinked = Employee::withTrashed()
                    ->where('user_id', $this->integer('user_id'))
                    ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                    ->exists();
                if ($alreadyLinked) {
                    $validator->errors()->add('user_id', 'حساب المستخدم مرتبط بموظف آخر.');
                }
            }

            foreach ($this->input('skills', []) as $index => $skill) {
                $available = \App\Models\BranchService::query()
                    ->where('company_id', app(TenantContext::class)->companyId())
                    ->where('branch_id', $branch->id)
                    ->where('service_id', $skill['service_id'] ?? 0)
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->exists();
                if (! $available) {
                    $validator->errors()->add("skills.$index.service_id", 'الخدمة غير متاحة في فرع الموظف.');
                }
            }
        });
    }
}
