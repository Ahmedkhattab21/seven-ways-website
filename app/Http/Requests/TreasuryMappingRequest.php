<?php

namespace App\Http\Requests;

class TreasuryMappingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'operation_type' => ['required', 'in:receipt,payment,refund,deposit,withdrawal,transfer,merchant_settlement'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'clearing_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'fees_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'settlement_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
