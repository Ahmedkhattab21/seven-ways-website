<?php

namespace App\Http\Requests;

class BankMatchingRuleRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'], 'name' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:1', 'max:100000'],
            'condition_type' => ['required', 'in:description_contains,reference_contains,reference_prefix,reference_exact,transaction_code,counterparty_iban_last4,amount_range'],
            'condition_value' => ['nullable', 'string', 'max:255'], 'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'gte:amount_min'], 'direction' => ['nullable', 'in:debit,credit'],
            'transaction_code' => ['nullable', 'string', 'max:50'],
            'result_type' => ['required', 'in:suggest_match,suggest_account,suggest_customer,suggest_supplier,suggest_adjustment,ignore'],
            'suggested_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'suggested_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'suggested_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'suggested_operation_type' => ['nullable', 'string', 'max:40'], 'auto_match' => ['required', 'boolean'],
            'minimum_confidence' => ['required', 'integer', 'between:0,100'], 'is_active' => ['required', 'boolean'],
        ]);
    }
}
