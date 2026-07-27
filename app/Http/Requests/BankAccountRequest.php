<?php

namespace App\Http\Requests;

class BankAccountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'account_code' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:50'],
            'account_number_masked' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'gl_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_fees_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'interest_income_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'interest_expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'unidentified_receipts_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'unidentified_payments_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'account_type' => ['required', 'in:current,savings,merchant,collection,payroll,credit_card,other'],
            'opening_date' => ['nullable', 'date'],
            'closing_date' => ['prohibited'],
            'is_primary' => ['sometimes', 'boolean'],
            'allows_receipts' => ['sometimes', 'boolean'],
            'allows_payments' => ['sometimes', 'boolean'],
            'allows_transfers' => ['sometimes', 'boolean'],
            'requires_reconciliation' => ['sometimes', 'boolean'],
            'last_reconciled_date' => ['prohibited'],
            'book_balance' => ['prohibited'],
            'bank_balance' => ['prohibited'],
        ]);
    }
}
