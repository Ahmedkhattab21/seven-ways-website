<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeServiceSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'skill_level' => ['required', Rule::in(['trainee', 'junior', 'intermediate', 'senior', 'expert'])],
            'is_primary' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'certified_at' => ['nullable', 'date'], 'certification_expires_at' => ['nullable', 'date', 'after_or_equal:certified_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
