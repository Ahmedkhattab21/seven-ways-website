<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.record_waste') ?? false;
    }

    public function rules(): array
    {
        return ['work_order_service_id' => ['nullable', 'integer', 'exists:work_order_services,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id'], 'roll_id' => ['nullable', 'integer', 'exists:inventory_rolls,id'], 'scrap_id' => ['nullable', 'integer', 'exists:roll_scraps,id'], 'quantity' => ['nullable', 'numeric', 'gt:0'], 'area' => ['nullable', 'numeric', 'gt:0'], 'unit_cost' => ['required', 'numeric', 'min:0'], 'reason_code' => ['required', Rule::in(['normal_cutting', 'installation_error', 'damaged_material', 'measurement_error', 'customer_change', 'defective_product', 'other'])], 'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'requires_approval' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string']];
    }
}
