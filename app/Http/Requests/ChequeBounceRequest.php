<?php

namespace App\Http\Requests;

class ChequeBounceRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'date' => ['required', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
    }
}
