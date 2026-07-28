<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnalyticsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return [
            'company_id' => ['prohibited'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'branch_ids' => ['nullable', 'array', 'max:50'],
            'branch_ids.*' => [Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'status' => ['nullable', 'string', 'max:40'],
            'movement_days' => ['nullable', 'integer', 'between:1,3650'],
            'sort' => ['nullable', 'string', 'max:80'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'print', 'pdf'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('date_from') && $this->filled('date_to')
                && Carbon::parse($this->date_from)->diffInDays(Carbon::parse($this->date_to)) > 366) {
                $validator->errors()->add('date_to', 'Maximum report range is 366 days.');
            }
        });
    }
}
