<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyClaimInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('warranty_claims.inspect');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.inspection_result' => ['required', 'in:installation_defect,material_defect,misuse,accident,normal_wear,undetermined'],
            'items.*.notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
