<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderRollConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.consume_roll') ?? false;
    }

    public function rules(): array
    {
        return ['length' => ['required', 'numeric', 'gt:0'], 'usable_area' => ['required', 'numeric', 'min:0'], 'waste_area' => ['nullable', 'numeric', 'min:0']];
    }
}
