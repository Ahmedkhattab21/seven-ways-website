<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', auth()->user()->company_id)
                    ->where('is_active', true),
            ],
            'service_category_id' => ['required', 'integer'],
            'code' => ['nullable', 'alpha_dash', 'max:50', Rule::unique('services')
                ->where('company_id', auth()->user()->company_id)->ignore($this->route('service'))],
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'service_type' => ['required', Rule::in([
                'ppf', 'thermal_insulation', 'tint', 'glass_protection', 'interior_protection',
                'detailing', 'removal', 'maintenance', 'other',
            ])],
            'pricing_type' => ['required', Rule::in(['fixed', 'by_vehicle_size', 'by_vehicle_type', 'custom_quote', 'per_unit'])],
            'default_duration_minutes' => ['required', 'integer', 'min:1'],
            'default_tax_id' => ['nullable', 'integer'],
            'pricing_unit_id' => ['nullable', 'integer'],
            'default_warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'requires_vehicle' => ['sometimes', 'boolean'],
            'requires_inspection' => ['sometimes', 'boolean'],
            'requires_quality_check' => ['sometimes', 'boolean'],
            'allows_multiple_technicians' => ['sometimes', 'boolean'],
            'is_package_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
