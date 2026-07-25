<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRollReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rolls' => ['required', 'array', 'min:1'], 'rolls.*.supplier_roll_number' => ['nullable', 'string', 'max:255'],
            'rolls.*.batch_number' => ['nullable', 'string', 'max:100'], 'rolls.*.width' => ['required', 'numeric', 'gt:0'],
            'rolls.*.length' => ['required', 'numeric', 'gt:0'], 'rolls.*.manufacturing_date' => ['nullable', 'date'],
            'rolls.*.expiry_date' => ['nullable', 'date'],
        ];
    }
}
