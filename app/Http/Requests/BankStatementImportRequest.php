<?php

namespace App\Http\Requests;

class BankStatementImportRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'profile_id' => ['required', 'integer', 'exists:bank_statement_import_profiles,id'],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
            'statement_reference' => ['nullable', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['required', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'closing_balance' => ['required', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ]);
    }
}
