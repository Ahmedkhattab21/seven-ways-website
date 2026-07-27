<?php

namespace App\Http\Requests;

class TreasuryTransferReversalRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'date' => ['nullable', 'date'], 'reversed_by' => ['prohibited'], 'reversed_at' => ['prohibited'],
        ]);
    }
}
