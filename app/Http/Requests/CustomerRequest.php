<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer
            ? $this->user()->can('update', $customer)
            : $this->user()->hasPermission('customers.create');
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $customer = $this->route('customer');
        $companyUnique = fn (string $column) => Rule::unique('customers', $column)
            ->where(fn (Builder $query) => $query->where('company_id', $companyId))
            ->ignore($customer);

        return [
            'customer_type' => ['required', Rule::in(['individual', 'company', 'car_showroom', 'rental_company', 'fleet'])],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => [Rule::requiredIf($this->input('customer_type') === 'individual'), 'nullable', 'string', 'max:40'],
            'alternative_phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100', $companyUnique('tax_number')],
            'commercial_registration' => ['nullable', 'string', 'max:100', $companyUnique('commercial_registration')],
            'preferred_language' => ['required', Rule::in(['ar', 'en'])],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:999999999999999.9999'],
            'payment_term_days' => ['required', 'integer', 'between:0,3650'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'source_id' => ['nullable', Rule::exists('customer_sources', 'id')->where('company_id', $companyId)],
            'assigned_branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }
}
