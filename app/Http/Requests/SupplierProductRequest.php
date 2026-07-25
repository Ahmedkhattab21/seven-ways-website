<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'], 'purchase_unit_id' => ['required', 'integer'],
            'supplier_sku' => ['nullable', 'string', 'max:255'], 'supplier_product_name' => ['nullable', 'string', 'max:255'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'], 'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'numeric', 'gt:0'], 'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
