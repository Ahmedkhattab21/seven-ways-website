<?php

namespace App\Http\Requests;

class OpeningBalanceRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['nullable', 'integer'], 'fiscal_year_id' => ['required', 'integer'],
            'balance_date' => ['required', 'date'], 'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
