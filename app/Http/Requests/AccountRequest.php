<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AccountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'account_type_id' => ['required', 'integer'], 'account_group_id' => ['nullable', 'integer'],
            'parent_account_id' => ['nullable', 'integer'], 'account_code' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'], 'is_header' => ['required', 'boolean'],
            'is_posting' => ['required', 'boolean'], 'normal_balance' => ['nullable', Rule::in(['debit', 'credit'])],
            'contra_reason' => ['nullable', 'string', 'max:1000'], 'currency_id' => ['nullable', 'integer'],
            'allows_multi_currency' => ['boolean'], 'requires_cost_center' => ['boolean'],
            'requires_branch' => ['boolean'], 'requires_customer' => ['boolean'], 'requires_supplier' => ['boolean'],
            'requires_employee' => ['boolean'], 'requires_vehicle' => ['boolean'], 'is_control_account' => ['boolean'],
            'control_type' => ['nullable', Rule::in([
                'accounts_receivable', 'accounts_payable', 'inventory', 'vat_input', 'vat_output',
                'customer_advances', 'supplier_advances', 'employee_advances', 'fixed_assets',
                'accumulated_depreciation', 'none',
            ])],
            'is_bank_account' => ['boolean'], 'is_cash_account' => ['boolean'], 'is_inventory_account' => ['boolean'],
            'is_tax_account' => ['boolean'], 'is_active' => ['boolean'], 'allow_manual_entry' => ['boolean'],
        ]);
    }
}
