<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QualityCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('quality_checks.create');
    }

    public function rules(): array
    {
        return ['template_id' => ['nullable', 'integer', 'exists:quality_checklist_templates,id']];
    }
}
