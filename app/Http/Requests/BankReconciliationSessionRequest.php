<?php

namespace App\Http\Requests;

class BankReconciliationSessionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'import_ids' => ['required', 'array', 'min:1'], 'import_ids.*' => ['integer', 'distinct', 'exists:bank_statement_imports,id'],
            'tolerance' => ['required', 'numeric', 'min:0'], 'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
