<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyClaimRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => collect($this->input('items', []))
                ->filter(fn ($item) => filled($item['warranty_item_id'] ?? null))
                ->values()->all(),
        ]);
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('warranty_claims.create');
    }

    public function rules(): array
    {
        return [
            'warranty_id' => ['required', 'integer', 'exists:warranties,id'],
            'complaint' => ['required', 'string', 'max:5000'],
            'inspection_scheduled_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.warranty_item_id' => ['required', 'integer', 'distinct', 'exists:warranty_items,id'],
            'items.*.issue_type' => ['required', 'in:peeling,bubbles,discoloration,cracking,scratches,adhesive_failure,installation_defect,material_defect,other'],
            'items.*.description' => ['required', 'string', 'max:3000'],
        ];
    }
}
