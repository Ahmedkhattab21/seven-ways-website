<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('user') ? 'users.update' : 'users.create';

        return $this->user()->hasPermission($permission) || $this->user()->hasRole('system_admin');
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $companyId = app(TenantContext::class)->companyId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:10', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [Rule::exists('roles', 'id')->where(fn ($query) => $query->where(fn ($inner) => $inner->whereNull('company_id')->orWhere('company_id', $companyId))->where('is_active', true))],
            'assign_as_responsible' => ['nullable', 'boolean'],
            'responsible_branch_id' => ['nullable', Rule::exists('branches', 'id')->where(
                fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
            )],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $branchIds = collect($this->input('branch_ids', []))->map(fn ($id) => (int) $id);
            $defaultBranch = $this->integer('branch_id');

            if ($defaultBranch && ! $branchIds->contains($defaultBranch)) {
                $validator->errors()->add('branch_id', 'الفرع الافتراضي يجب أن يكون ضمن الفروع المسموحة.');
            }

            if (! $user->isCompanyAdministrator() && ! $user->hasRole('system_admin')) {
                $allowed = app(TenantContext::class)->accessibleBranches()->pluck('id');
                if ($branchIds->diff($allowed)->isNotEmpty()) {
                    $validator->errors()->add('branch_ids', 'لا يمكنك منح وصول لفرع خارج نطاقك.');
                }

                $forbiddenRole = \App\Models\Role::query()->whereIn('id', $this->input('role_ids', []))
                    ->whereIn('name', ['system_admin', 'company_owner', 'general_manager'])->exists();
                if ($forbiddenRole) {
                    $validator->errors()->add('role_ids', 'لا يمكنك تعيين دور إداري أعلى.');
                }
            }
            if ($this->boolean('assign_as_responsible')) {
                if (! $user->hasRole(['company_owner', 'general_manager', 'system_admin'])) {
                    $validator->errors()->add('assign_as_responsible', 'لا تملك صلاحية تعيين مسؤول تشغيل الفرع.');
                }

                $responsibleBranchId = $this->integer('responsible_branch_id');
                if (! $responsibleBranchId || ! $branchIds->contains($responsibleBranchId)
                    || $defaultBranch !== $responsibleBranchId) {
                    $validator->errors()->add('responsible_branch_id', 'يجب أن يكون فرع المسؤول هو الفرع الافتراضي والمتاح.');
                }

                $hasBranchManagerRole = \App\Models\Role::query()
                    ->whereIn('id', $this->input('role_ids', []))
                    ->where('name', 'branch_manager')
                    ->exists();
                if (! $hasBranchManagerRole) {
                    $validator->errors()->add('role_ids', 'يجب اختيار دور مسؤول الفرع.');
                }
            }
        });
    }
}
