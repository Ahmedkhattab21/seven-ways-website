<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServicePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'], 'vehicle_size_id' => ['nullable', 'integer'],
            'vehicle_type_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'],
            'price' => ['required', 'numeric', 'min:0'], 'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'between:-1000,1000'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
