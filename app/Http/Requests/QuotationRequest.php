<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'], 'lead_id' => ['nullable', 'integer'],
            'customer_id' => ['required', 'integer'], 'vehicle_id' => ['required', 'integer'],
            'quotation_date' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'currency_id' => ['required', 'integer'], 'price_includes_tax' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'customer_notes' => ['nullable', 'string', 'max:5000'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.item_type' => ['required', Rule::in(['service', 'package', 'product', 'custom'])],
            'items.*.service_id' => ['nullable', 'integer'], 'items.*.service_package_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'], 'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.manual_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.promotion_id' => ['nullable', 'integer'],
        ];
    }
}
