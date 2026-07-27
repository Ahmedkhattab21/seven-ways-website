<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BankStatementImportProfileRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'name' => ['required', 'string', 'max:255'], 'format' => ['required', 'in:csv'],
            'delimiter' => ['required', Rule::in([',', ';', '|', "\t"])], 'encoding' => ['required', 'in:UTF-8'],
            'date_format' => ['required', 'in:Y-m-d,d/m/Y,m/d/Y,d-m-Y'],
            'decimal_separator' => ['required', 'in:.,,'],
            'thousands_separator' => ['nullable', 'in:.,,'],
            'has_header' => ['required', 'boolean'], 'column_mapping' => ['required', 'array'],
            'column_mapping.transaction_date' => ['required'], 'column_mapping.description' => ['required'],
            'skip_rows' => ['required', 'integer', 'min:0', 'max:100'],
            'direction_policy' => ['required', 'in:credit_increases,debit_increases'],
            'balance_tolerance' => ['required', 'numeric', 'min:0'],
            'is_default' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
    }
}
