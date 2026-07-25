<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_type' => ['required', 'in:billing,shipping,office,warehouse,other'],
            'country_id' => ['nullable', 'integer'], 'city_id' => ['nullable', 'integer'],
            'address_line' => ['required', 'string', 'max:500'], 'postal_code' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
