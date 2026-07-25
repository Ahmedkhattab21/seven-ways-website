<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'], 'role_id' => ['nullable', 'integer'],
            'commission_type' => ['required', Rule::in(['fixed', 'percentage', 'per_vehicle', 'per_unit'])],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'calculation_base' => ['required', Rule::in(['service_price', 'net_service_price', 'gross_profit', 'fixed'])],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'], 'maximum_amount' => ['nullable', 'numeric', 'gte:minimum_amount'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->commission_type === 'percentage' && (float) $this->commission_value > 100) {
                $validator->errors()->add('commission_value', 'Percentage commission cannot exceed 100.');
            }
        });
    }
}
