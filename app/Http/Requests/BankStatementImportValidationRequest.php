<?php

namespace App\Http\Requests;

class BankStatementImportValidationRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['required', 'string', 'max:1000']]);
    }
}
