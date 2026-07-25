<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}goods_receipt_item_id" => ['nullable', 'integer'], "{$prefix}product_id" => ['nullable', 'integer'],
            "{$prefix}roll_id" => ['nullable', 'integer'], "{$prefix}batch_id" => ['nullable', 'integer'],
            "{$prefix}unit_id" => ['nullable', 'integer'], "{$prefix}quantity" => ['required', 'numeric', 'gt:0'],
            "{$prefix}unit_cost" => ['nullable', 'numeric', 'min:0'], "{$prefix}tax_rate" => ['nullable', 'numeric', 'between:0,100'],
            "{$prefix}reason_code" => ['required', 'in:damaged,expired,wrong_item,quality_failure,excess,supplier_request,other'],
            "{$prefix}notes" => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }
}
