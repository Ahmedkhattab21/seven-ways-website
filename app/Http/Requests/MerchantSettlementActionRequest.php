<?php

namespace App\Http\Requests;

class MerchantSettlementActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['nullable', 'string', 'min:5', 'max:2000']]);
    }
}
