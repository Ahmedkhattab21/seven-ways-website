<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employee_expenses.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer'],
            'payable_account_id' => ['required', 'integer'],
            'cost_center_id' => ['nullable', 'integer'],
            'work_order_id' => ['nullable', 'integer'],
            'claim_date' => ['required', 'date'],
            'business_purpose' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.expense_category_id' => ['nullable', 'integer'],
            'items.*.expense_account_id' => ['required', 'integer'],
            'items.*.tax_id' => ['nullable', 'integer'],
            'items.*.expense_date' => ['required', 'date'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.net_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
