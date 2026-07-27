<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('commissions.manage_rules') ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'currency_id' => ['required', 'integer'],
            'expense_account_id' => ['required', 'integer'],
            'payable_account_id' => ['required', 'integer'],
            'rule_type' => ['required', Rule::in([
                'percentage_net_sales', 'percentage_margin', 'fixed_product', 'fixed_service', 'fixed',
            ])],
            'rule_value' => ['required', 'numeric', 'gt:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric', 'gte:minimum_amount'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'between:-1000,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
