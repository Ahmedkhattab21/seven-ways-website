<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'country' => ['required', Rule::in(['saudi_arabia', 'egypt'])],
            'city' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:120'],
            'vehicle_model' => ['required', 'string', 'max:120'],
            'vehicle_year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'service' => ['required', Rule::in(['ppf', 'thermal', 'nano', 'polishing', 'other'])],
            'preferred_branch' => [
                'nullable',
                'string',
                Rule::in(array_column(config('website.branches', []), 'id')),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function attributes(): array
    {
        return __('website.registration.fields');
    }

    public function messages(): array
    {
        return [
            'required' => __('website.registration.validation.required'),
            'email' => __('website.registration.validation.email'),
            'phone.regex' => __('website.registration.validation.phone'),
            'in' => __('website.registration.validation.invalid'),
            'integer' => __('website.registration.validation.year'),
            'min' => __('website.registration.validation.year'),
            'max' => __('website.registration.validation.max'),
            'website.max' => __('website.registration.validation.spam'),
        ];
    }
}
