<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'], 'vehicle_id' => ['nullable', 'integer'],
            'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_type' => ['nullable', 'in:fixed,percentage'], 'discount_value' => ['nullable', 'numeric', 'min:0'],
            'terms_snapshot' => ['nullable', 'string', 'max:5000'], 'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:service,product,custom'], 'items.*.product_id' => ['nullable', 'integer'],
            'items.*.warehouse_id' => ['nullable', 'integer'], 'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'], 'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
