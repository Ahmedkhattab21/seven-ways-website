<?php

namespace App\Http\Requests;

class BankStatementLineActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'reason' => ['required', 'string', 'max:1000'],
            'duplicate_of_id' => ['nullable', 'integer', 'exists:bank_statement_lines,id'],
        ]);
    }
}
