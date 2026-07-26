<?php

namespace App\Http\Requests;

class FiscalYearRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['boolean'],
        ]);
    }
}
