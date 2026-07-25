<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer'], 'supplier_invoice_id' => ['nullable', 'integer'],
            'purchase_return_id' => ['nullable', 'integer'], 'supplier_credit_number' => ['nullable', 'string', 'max:255'],
            'credit_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.supplier_invoice_item_id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'], 'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
