<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'], 'currency_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer'], 'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'], 'reference_number' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'in:manual,bank_transfer,cash,card,other'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
