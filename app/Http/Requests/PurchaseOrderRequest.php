<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'], 'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'currency_id' => ['nullable', 'integer'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'], 'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'shipping_address_snapshot' => ['nullable', 'string', 'max:2000'], 'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'], 'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'], 'rounding_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:5000'], 'terms_snapshot' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
        ] + PurchaseOrderItemRequest::itemRules('items.*.');
    }
}
