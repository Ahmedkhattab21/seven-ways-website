<?php

namespace App\Http\Requests;

class BankAdjustmentRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'bank_reconciliation_session_id' => ['nullable', 'integer', 'exists:bank_reconciliation_sessions,id'],
            'bank_statement_line_id' => ['nullable', 'integer', 'exists:bank_statement_lines,id'],
            'adjustment_type' => ['required', 'in:bank_fee,interest_income,interest_expense,unidentified_receipt,unidentified_payment,rounding,other'],
            'adjustment_date' => ['required', 'date'], 'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'], 'offset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'description' => ['required', 'string', 'max:2000'], 'reference' => ['nullable', 'string', 'max:255'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
