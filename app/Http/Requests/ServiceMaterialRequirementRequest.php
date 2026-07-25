<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceMaterialRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_size_id' => ['nullable', 'integer'], 'vehicle_type_id' => ['nullable', 'integer'],
            'product_id' => ['required', 'integer'], 'unit_id' => ['required', 'integer'],
            'requirement_type' => ['required', Rule::in([
                'primary_film', 'secondary_film', 'installation_material', 'consumable', 'accessory', 'tool', 'other',
            ])],
            'expected_quantity' => ['required', 'numeric', 'gt:0'],
            'expected_waste_percentage' => ['required', 'numeric', 'between:0,100'],
            'minimum_quantity' => ['nullable', 'numeric', 'gt:0'],
            'maximum_quantity' => ['nullable', 'numeric', 'gte:minimum_quantity'],
            'is_required' => ['sometimes', 'boolean'], 'allow_substitution' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
