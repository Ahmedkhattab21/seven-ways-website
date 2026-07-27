<?php

namespace App\Http\Requests;

class BankAccountBranchAccessRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'can_view' => ['sometimes', 'boolean'],
            'can_receive' => ['sometimes', 'boolean'],
            'can_pay' => ['sometimes', 'boolean'],
            'can_transfer' => ['sometimes', 'boolean'],
            'daily_payment_limit' => ['nullable', 'numeric', 'min:0'],
            'daily_transfer_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
