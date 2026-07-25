<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'], 'is_available' => ['sometimes', 'boolean'],
            'booking_enabled' => ['sometimes', 'boolean'], 'requires_approval' => ['sometimes', 'boolean'],
            'minimum_notice_minutes' => ['nullable', 'integer', 'min:0'],
            'maximum_daily_capacity' => ['nullable', 'integer', 'min:1'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
