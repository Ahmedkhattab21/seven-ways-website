<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'alpha_dash', 'max:50', Rule::unique('promotions')
                ->where('company_id', auth()->user()->company_id)->ignore($this->route('promotion'))],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'promotion_type' => ['required', Rule::in(['service', 'package', 'product', 'general'])],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage', 'fixed_price'])],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'start_at' => ['required', 'date'], 'end_at' => ['required', 'date', 'after:start_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'], 'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'], 'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'distinct'], 'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'distinct'], 'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->discount_type === 'percentage' && (float) $this->discount_value > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100.');
            }
            if ($this->discount_type === 'fixed_price' && $this->promotion_type !== 'product') {
                $validator->errors()->add('discount_type', 'Fixed promotional prices are available for products only.');
            }
        });
    }
}
