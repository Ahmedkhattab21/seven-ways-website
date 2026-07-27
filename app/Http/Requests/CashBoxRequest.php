<?php

namespace App\Http\Requests;

class CashBoxRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'gl_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'over_short_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_primary' => ['sometimes', 'boolean'],
            'allows_receipts' => ['sometimes', 'boolean'],
            'allows_payments' => ['sometimes', 'boolean'],
            'requires_shift_opening' => ['sometimes', 'boolean'],
            'maximum_cash_limit' => ['nullable', 'numeric', 'min:0'],
            'minimum_cash_limit' => ['nullable', 'numeric', 'min:0'],
            'book_balance' => ['prohibited'],
        ]);
    }
}
