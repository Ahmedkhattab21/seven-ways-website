<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyClaimDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('warranty_claims.decide');
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:covered,partially_covered,not_covered,goodwill'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.coverage_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
