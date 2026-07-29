<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('branch') ? 'branches.update' : 'branches.create';

        return $this->user()->hasPermission($permission) || $this->user()->hasRole('system_admin');
    }

    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('branches')->where('company_id', app(TenantContext::class)->companyId())->ignore($branch)],
            'name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'responsible_name' => ['nullable', 'required_with:responsible_email', 'string', 'max:255'],
            'responsible_email' => ['nullable', 'required_with:responsible_name', 'email', 'max:255', 'unique:users,email'],
            'responsible_password' => ['nullable', 'required_with:responsible_email', 'string', 'min:10', 'confirmed'],
            'responsible_password_confirmation' => ['nullable', 'string'],
            'responsible_status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
