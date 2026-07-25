<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoodsReceiptInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'], 'items.*.accepted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.rejected_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged,expired,incorrect,short_received,over_received,other'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
