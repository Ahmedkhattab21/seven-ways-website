<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
            'acceptance_method' => ['nullable', Rule::in(['in_person', 'phone', 'whatsapp', 'email', 'system'])],
            'accepted_by_name' => ['nullable', 'string', 'max:255'],
            'acceptance_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
