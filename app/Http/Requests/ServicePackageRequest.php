<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServicePackageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('items') && is_array($this->input('service_ids'))) {
            $this->merge([
                'items' => collect($this->input('service_ids'))->map(
                    fn ($serviceId) => ['service_id' => $serviceId, 'quantity' => 1]
                )->values()->all(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'alpha_dash', 'max:50', Rule::unique('service_packages')
                ->where('company_id', auth()->user()->company_id)->ignore($this->route('servicePackage'))],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'package_type' => ['required', Rule::in(['fixed', 'vehicle_size', 'custom'])],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('services', 'id')->where('company_id', auth()->user()->company_id)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'branch_id' => ['nullable', 'required_with:price', 'integer'],
            'vehicle_size_id' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'effective_from' => ['nullable', 'required_with:price', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
