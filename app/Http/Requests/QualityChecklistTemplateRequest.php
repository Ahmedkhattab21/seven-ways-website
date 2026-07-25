<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QualityChecklistTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => collect($this->input('items', []))
                ->filter(fn ($item) => filled($item['code'] ?? null) || filled($item['name'] ?? null))
                ->values()->all(),
        ]);
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('quality_checks.manage_templates');
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_type' => ['nullable', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:80', 'distinct'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.category' => ['required', 'string', 'max:60'],
            'items.*.check_type' => ['required', 'in:pass_fail,rating,text,measurement,photo'],
            'items.*.is_required' => ['sometimes', 'boolean'],
            'items.*.is_critical' => ['sometimes', 'boolean'],
            'items.*.requires_photo_on_failure' => ['sometimes', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
