<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderMaterialReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.return') ?? false;
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'numeric', 'gt:0']];
    }
}
