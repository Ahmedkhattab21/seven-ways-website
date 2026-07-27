<?php

namespace App\Http\Requests;

class ChequeRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'direction' => ['required', 'in:received,issued'],
            'cheque_number' => ['required', 'string', 'max:100'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'drawer_name' => ['nullable', 'string', 'max:255'],
            'drawer_bank_name' => ['nullable', 'string', 'max:255'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'], 'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'received_date' => ['nullable', 'date'],
            'clearing_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'offset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'source_type' => ['prohibited'], 'source_id' => ['prohibited'],
            'cheque_scope_key' => ['prohibited'], 'bank_gl_account_id' => ['prohibited'],
            'clearance_journal_entry_id' => ['prohibited'], 'bounce_journal_entry_id' => ['prohibited'],
        ]);
    }
}
