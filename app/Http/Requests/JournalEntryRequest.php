<?php

namespace App\Http\Requests;

class JournalEntryRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:150'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'lines.*.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'lines.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'lines.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.debit_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lines.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'journal_number' => ['prohibited'], 'entry_type' => ['prohibited'],
            'source_type' => ['prohibited'], 'source_id' => ['prohibited'],
            'accounting_period_id' => ['prohibited'], 'fiscal_year_id' => ['prohibited'],
            'posted_by' => ['prohibited'], 'reversed_by' => ['prohibited'],
            'base_total_debit' => ['prohibited'], 'base_total_credit' => ['prohibited'],
        ]);
    }
}
