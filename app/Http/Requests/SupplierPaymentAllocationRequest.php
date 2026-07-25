<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierPaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['supplier_invoice_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0']];
    }
}
