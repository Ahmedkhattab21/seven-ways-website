<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer'], 'supplier_id' => ['required', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'], 'receipt_date' => ['required', 'date'],
            'supplier_delivery_note' => ['nullable', 'string', 'max:255'],
            'supplier_invoice_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1'],
        ] + GoodsReceiptItemRequest::itemRules('items.*.');
    }
}
