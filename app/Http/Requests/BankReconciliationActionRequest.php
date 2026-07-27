<?php

namespace App\Http\Requests;

class BankReconciliationActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
