<?php

namespace App\Http\Requests;

class CashBoxCustodianRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        if ($this->routeIs('treasury.cash-box-custodians.revoke')) {
            return $this->withProtected(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        }

        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'can_receive' => ['sometimes', 'boolean'],
            'can_pay' => ['sometimes', 'boolean'],
            'can_transfer' => ['sometimes', 'boolean'],
            'payment_limit' => ['nullable', 'numeric', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['prohibited'],
        ];

        if ($this->routeIs('treasury.cash-box-custodians.update')) {
            unset($rules['user_id'], $rules['employee_id'], $rules['valid_from']);
            $rules['valid_to'] = ['nullable', 'date'];
        }

        return $this->withProtected($rules);
    }
}
