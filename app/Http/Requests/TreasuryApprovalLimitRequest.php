<?php

namespace App\Http\Requests;

class TreasuryApprovalLimitRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id', 'required_without:user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:role_id'],
            'operation_type' => ['required', 'in:treasury_transfer,cash_receipt,cash_payment,cash_over_short,received_cheque,issued_cheque,cheque_clearance,cheque_bounce,merchant_settlement'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric', 'gte:minimum_amount'],
            'approval_level' => ['required', 'integer', 'min:1', 'max:20'],
            'can_create' => ['required', 'boolean'], 'can_submit' => ['required', 'boolean'],
            'can_approve' => ['required', 'boolean'], 'can_post' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'], 'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);
    }
}
