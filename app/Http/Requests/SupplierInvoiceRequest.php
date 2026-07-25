<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'], 'purchase_order_id' => ['nullable', 'integer'],
            'goods_receipt_id' => ['nullable', 'integer'], 'supplier_invoice_number' => ['required', 'string', 'max:100'],
            'currency_id' => ['nullable', 'integer'], 'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'], 'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'], 'other_charges' => ['nullable', 'numeric', 'min:0'],
            'rounding_amount' => ['nullable', 'numeric'], 'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
        ] + SupplierInvoiceItemRequest::itemRules('items.*.');
    }
}
