<?php

namespace App\Http\Requests;

class CashBoxSessionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'cash_box_id' => ['required', 'integer', 'exists:cash_boxes,id'],
            'custodian_user_id' => ['required', 'integer', 'exists:users,id'],
            'business_date' => ['required', 'date'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
            'session_number' => ['prohibited'], 'active_guard' => ['prohibited'],
            'opening_book_balance' => ['prohibited'], 'opening_counted_balance' => ['prohibited'],
            'opening_difference' => ['prohibited'], 'closing_book_balance' => ['prohibited'],
            'closing_counted_balance' => ['prohibited'], 'closing_difference' => ['prohibited'],
        ]);
    }
}
