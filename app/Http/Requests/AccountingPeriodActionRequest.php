<?php

namespace App\Http\Requests;

class AccountingPeriodActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['required', 'string', 'max:2000']]);
    }
}
