<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierInvoiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}purchase_order_item_id" => ['nullable', 'integer'], "{$prefix}goods_receipt_item_id" => ['nullable', 'integer'],
            "{$prefix}product_id" => ['nullable', 'integer'], "{$prefix}description" => ['required', 'string', 'max:255'],
            "{$prefix}quantity" => ['required', 'numeric', 'gt:0'], "{$prefix}unit_id" => ['nullable', 'integer'],
            "{$prefix}unit_price" => ['required', 'numeric', 'min:0'], "{$prefix}tax_id" => ['nullable', 'integer'],
            "{$prefix}tax_rate" => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }
}
