<?php

namespace App\Http\Requests;

class OpeningBalanceActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->protectedRules();
    }
}
