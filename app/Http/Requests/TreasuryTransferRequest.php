<?php

namespace App\Http\Requests;

class TreasuryTransferRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'transfer_type' => ['nullable', 'in:transfer,cash_deposit,cash_withdrawal'],
            'from_type' => ['required', 'in:bank,cash_box'],
            'from_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id', 'required_if:from_type,bank', 'prohibited_unless:from_type,bank'],
            'from_cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id', 'required_if:from_type,cash_box', 'prohibited_unless:from_type,cash_box'],
            'to_type' => ['required', 'in:bank,cash_box'],
            'to_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id', 'required_if:to_type,bank', 'prohibited_unless:to_type,bank'],
            'to_cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id', 'required_if:to_type,cash_box', 'prohibited_unless:to_type,cash_box'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'destination_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'in:1,1.0,1.00,1.00000000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fees_amount' => ['nullable', 'numeric', 'min:0'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'journal_entry_id' => ['prohibited'],
        ]);
    }
}
