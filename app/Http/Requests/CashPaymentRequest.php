<?php

namespace App\Http\Requests;

class CashPaymentRequest extends CashReceiptRequest
{
    public function rules(): array
    {
        return $this->cashRules(
            'payment_type',
            'general_expense,employee_advance,employee_reimbursement,customer_refund_foundation,petty_cash,miscellaneous'
        );
    }
}
