<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_credit_note_id' => ['required', 'integer'], 'customer_payment_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer'], 'refund_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'], 'reference_number' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
