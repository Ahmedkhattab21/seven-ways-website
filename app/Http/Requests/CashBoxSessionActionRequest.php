<?php

namespace App\Http\Requests;

class CashBoxSessionActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'notes' => ['nullable', 'string', 'max:2000'],
            'counting_started_by' => ['prohibited'], 'counting_started_at' => ['prohibited'],
        ]);
    }
}
