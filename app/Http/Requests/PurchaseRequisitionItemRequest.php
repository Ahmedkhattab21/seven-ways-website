<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequisitionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}product_id" => ['required', 'integer'], "{$prefix}unit_id" => ['required', 'integer'],
            "{$prefix}requested_quantity" => ['required', 'numeric', 'gt:0'],
            "{$prefix}estimated_unit_cost" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}preferred_supplier_id" => ['nullable', 'integer'], "{$prefix}required_date" => ['nullable', 'date'],
            "{$prefix}purpose" => ['nullable', 'string', 'max:2000'], "{$prefix}notes" => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }
}
