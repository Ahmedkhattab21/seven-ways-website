<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['sales_invoice_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0']];
    }
}
