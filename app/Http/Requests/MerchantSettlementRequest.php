<?php

namespace App\Http\Requests;

class MerchantSettlementRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'settlement_reference' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'], 'period_end' => ['required', 'date'],
            'settlement_date' => ['required', 'date'], 'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'fees_amount' => ['nullable', 'numeric', 'min:0'], 'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.source_type' => ['required', 'in:customer_payment'],
            'lines.*.source_id' => ['required', 'integer', 'exists:customer_payments,id'],
            'lines.*.allocated_amount' => ['required', 'numeric', 'gt:0'],
            'gross_amount' => ['prohibited'], 'net_amount' => ['prohibited'],
            'lines.*.gross_amount' => ['prohibited'], 'lines.*.fees_share' => ['prohibited'],
            'lines.*.net_amount' => ['prohibited'], 'idempotency_key' => ['prohibited'],
        ]);
    }
}
