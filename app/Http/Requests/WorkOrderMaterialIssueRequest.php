<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderMaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.issue') ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'waste_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
