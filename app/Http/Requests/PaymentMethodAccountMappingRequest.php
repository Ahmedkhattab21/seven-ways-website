<?php

namespace App\Http\Requests;

class PaymentMethodAccountMappingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
