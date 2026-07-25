<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date'], 'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'priority' => ['required', 'in:low,normal,high,urgent'], 'department' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
        ] + PurchaseRequisitionItemRequest::itemRules('items.*.');
    }
}
