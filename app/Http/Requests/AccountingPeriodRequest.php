<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AccountingPeriodRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'fiscal_year_id' => ['required', 'integer'], 'period_number' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_adjustment_period' => ['boolean'], 'locked_modules' => ['nullable', 'array'],
            'locked_modules.*' => [Rule::in(['sales', 'purchasing', 'inventory', 'payments', 'manual_journals'])],
        ]);
    }
}
