<?php

namespace App\Http\Requests;

class CashReceiptRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->cashRules('receipt_type', 'other_income,employee_return,supplier_refund,capital_injection,loan_proceeds_foundation,miscellaneous');
    }

    protected function cashRules(string $type, string $values): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'cash_box_id' => ['required', 'integer', 'exists:cash_boxes,id'],
            'cash_box_session_id' => ['nullable', 'integer', 'exists:cash_box_sessions,id'],
            $type => ['required', 'in:'.$values], 'document_date' => ['required', 'date'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'in:1,1.0,1.00,1.00000000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'offset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'], 'idempotency_key' => ['prohibited'],
        ]);
    }
}
