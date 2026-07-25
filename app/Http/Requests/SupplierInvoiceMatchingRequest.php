<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierInvoiceMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['match_id' => ['required', 'integer'], 'approval_reason' => ['required', 'string', 'max:2000']];
    }
}
