<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'legal_name' => ['nullable', 'string', 'max:255'],
            'supplier_type' => ['required', Rule::in(config('purchasing.supplier_types'))],
            'tax_number' => ['nullable', 'string', 'max:60'], 'commercial_registration' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'], 'currency_id' => ['nullable', 'integer'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'], 'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
