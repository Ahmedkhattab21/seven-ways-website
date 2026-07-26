<?php

namespace App\Http\Requests;

class FiscalYearActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['nullable', 'string', 'max:2000']]);
    }
}
