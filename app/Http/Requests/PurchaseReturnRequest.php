<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'], 'warehouse_id' => ['required', 'integer'],
            'goods_receipt_id' => ['nullable', 'integer'], 'purchase_order_id' => ['nullable', 'integer'],
            'return_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
        ] + PurchaseReturnItemRequest::itemRules('items.*.');
    }
}
