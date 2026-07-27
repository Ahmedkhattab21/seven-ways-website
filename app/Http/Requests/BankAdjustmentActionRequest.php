<?php

namespace App\Http\Requests;

class BankAdjustmentActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'reason' => ['nullable', 'string', 'max:2000'], 'date' => ['nullable', 'date'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
