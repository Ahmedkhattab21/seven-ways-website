<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_invoice_id' => ['required', 'integer'], 'credit_note_date' => ['required', 'date'],
            'reason_code' => ['required', 'in:service_refund,pricing_error,customer_compensation,warranty_resolution,cancellation,duplicate_invoice,other'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
