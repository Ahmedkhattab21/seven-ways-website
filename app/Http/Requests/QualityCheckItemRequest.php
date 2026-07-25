<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QualityCheckItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('quality_checks.perform');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.result' => ['required', 'in:pending,passed,failed,not_applicable'],
            'items.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'items.*.measurement_value' => ['nullable', 'numeric'],
            'items.*.measurement_unit' => ['nullable', 'string', 'max:30'],
            'items.*.notes' => ['nullable', 'string', 'max:3000'],
            'items.*.not_applicable_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
