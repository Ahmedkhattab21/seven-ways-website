<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequisitionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:2000'],
            'approved_quantities' => ['nullable', 'array'], 'approved_quantities.*' => ['numeric', 'min:0'],
        ];
    }
}
