<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AccountTypeRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'code' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'],
            'classification' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'statement_type' => ['required', Rule::in(['balance_sheet', 'income_statement'])],
            'cash_flow_category' => ['nullable', Rule::in(['operating', 'investing', 'financing', 'none'])],
            'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['boolean'],
        ]);
    }
}
