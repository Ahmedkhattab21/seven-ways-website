<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class FiscalPeriodGenerationRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['frequency' => ['required', Rule::in(['monthly'])]]);
    }
}
