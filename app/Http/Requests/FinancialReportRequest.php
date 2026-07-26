<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return [
            'company_id' => ['prohibited'], 'raw_sql' => ['prohibited'], 'formula' => ['prohibited'],
            'date_from' => ['nullable', 'required_with:comparison', 'date'],
            'date_to' => ['nullable', 'required_with:comparison', 'date', 'after_or_equal:date_from'],
            'as_of_date' => ['nullable', 'date'], 'fiscal_year_id' => ['nullable', Rule::exists('fiscal_years', 'id')->where('company_id', $companyId)],
            'accounting_period_id' => ['nullable', Rule::exists('accounting_periods', 'id')->where('company_id', $companyId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'account_group_id' => ['nullable', Rule::exists('account_groups', 'id')->where('company_id', $companyId)],
            'account_type_id' => ['nullable', 'integer', 'exists:account_types,id'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'], 'source_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'])],
            'entry_type' => ['nullable', 'string', 'max:40'], 'journal_number' => ['nullable', 'string', 'max:80'],
            'include_zero' => ['nullable', 'boolean'], 'include_header' => ['nullable', 'boolean'],
            'view_type' => ['nullable', Rule::in(['adjusted', 'unadjusted'])],
            'summary_by' => ['nullable', Rule::in(['account', 'group', 'type'])],
            'comparison' => ['nullable', Rule::in(['previous_period', 'previous_year'])],
            'export' => ['nullable', Rule::in(['html', 'csv'])], 'per_page' => ['nullable', 'integer', 'between:1,200'],
            'reconciliation_type' => ['nullable', Rule::in(['customers', 'suppliers', 'inventory', 'vat_output', 'vat_input'])],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->filled('branch_id')
            && ! app(TenantContext::class)->accessibleBranches()->contains('id', (int) $this->input('branch_id'))) {
            abort(403);
        }
    }
}
