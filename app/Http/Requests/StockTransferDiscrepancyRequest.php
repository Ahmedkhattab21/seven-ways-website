<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_transfer_item_id' => ['required', 'integer', 'exists:stock_transfer_items,id'],
            'discrepancy_type' => ['required', Rule::in(['shortage', 'excess', 'damage', 'wrong_item', 'wrong_roll', 'other'])],
            'quantity' => ['nullable', 'numeric', 'gt:0'], 'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
