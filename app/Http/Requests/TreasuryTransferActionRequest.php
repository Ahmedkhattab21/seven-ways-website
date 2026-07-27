<?php

namespace App\Http\Requests;

class TreasuryTransferActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'reason' => [$this->route('action') === 'cancel' ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
    }
}
