<?php

namespace App\Http\Requests;

class CashOverShortActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'description' => ['nullable', 'string', 'min:5', 'max:2000'],
            'reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'amount' => ['prohibited'], 'adjustment_type' => ['prohibited'],
        ]);
    }
}
