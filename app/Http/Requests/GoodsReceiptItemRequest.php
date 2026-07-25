<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoodsReceiptItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}purchase_order_item_id" => ['nullable', 'integer'], "{$prefix}product_id" => ['nullable', 'integer'],
            "{$prefix}unit_id" => ['nullable', 'integer'], "{$prefix}conversion_factor" => ['nullable', 'numeric', 'gt:0'],
            "{$prefix}received_quantity" => ['required', 'numeric', 'gt:0'], "{$prefix}accepted_quantity" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}rejected_quantity" => ['nullable', 'numeric', 'min:0'], "{$prefix}free_quantity" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}unit_cost" => ['nullable', 'numeric', 'min:0'], "{$prefix}tax_rate" => ['nullable', 'numeric', 'between:0,100'],
            "{$prefix}batch_number" => ['nullable', 'string', 'max:100'], "{$prefix}manufacture_date" => ['nullable', 'date'],
            "{$prefix}expiry_date" => ['nullable', 'date'], "{$prefix}serial_number" => ['nullable', 'string', 'max:255'],
            "{$prefix}roll_count" => ['nullable', 'integer', 'min:1'], "{$prefix}rolls" => ['nullable', 'array'],
            "{$prefix}rolls.*.supplier_roll_number" => ['nullable', 'string', 'max:255'],
            "{$prefix}rolls.*.width" => ['required_with:'.$prefix.'rolls', 'numeric', 'gt:0'],
            "{$prefix}rolls.*.length" => ['required_with:'.$prefix.'rolls', 'numeric', 'gt:0'],
            "{$prefix}condition" => ['nullable', 'in:good,damaged,expired,incorrect,short_received,over_received,other'],
            "{$prefix}rejection_reason" => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }
}
