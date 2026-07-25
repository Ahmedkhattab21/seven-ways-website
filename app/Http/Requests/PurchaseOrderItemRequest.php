<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}product_id" => ['required', 'integer'], "{$prefix}purchase_requisition_item_id" => ['nullable', 'integer'],
            "{$prefix}description" => ['nullable', 'string', 'max:255'], "{$prefix}purchase_unit_id" => ['nullable', 'integer'],
            "{$prefix}conversion_factor" => ['nullable', 'numeric', 'gt:0'], "{$prefix}ordered_quantity" => ['required', 'numeric', 'gt:0'],
            "{$prefix}unit_price" => ['required', 'numeric', 'min:0'], "{$prefix}discount_type" => ['nullable', 'in:fixed,percentage'],
            "{$prefix}discount_value" => ['nullable', 'numeric', 'min:0'], "{$prefix}tax_id" => ['nullable', 'integer'],
            "{$prefix}tax_rate" => ['nullable', 'numeric', 'between:0,100'], "{$prefix}expected_roll_count" => ['nullable', 'integer', 'min:1'],
            "{$prefix}expected_roll_width" => ['nullable', 'numeric', 'gt:0'], "{$prefix}expected_roll_length" => ['nullable', 'numeric', 'gt:0'],
            "{$prefix}batch_required" => ['sometimes', 'boolean'], "{$prefix}expiry_required" => ['sometimes', 'boolean'],
            "{$prefix}notes" => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }
}
