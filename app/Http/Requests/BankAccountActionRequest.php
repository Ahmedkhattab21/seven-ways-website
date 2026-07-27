<?php

namespace App\Http\Requests;

class BankAccountActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
    }
}
