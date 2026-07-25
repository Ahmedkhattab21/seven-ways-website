<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'branch' => [
                'nullable',
                'string',
                Rule::in(array_column(config('website.branches', []), 'id')),
            ],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function attributes(): array
    {
        return __('website.form.fields');
    }

    public function messages(): array
    {
        return [
            'required' => __('website.form.validation.required'),
            'email' => __('website.form.validation.email'),
            'max' => __('website.form.validation.max'),
            'message.min' => __('website.form.validation.message_min'),
            'phone.regex' => __('website.form.validation.phone'),
            'branch.in' => __('website.form.validation.branch'),
            'website.max' => __('website.form.validation.spam'),
        ];
    }
}
