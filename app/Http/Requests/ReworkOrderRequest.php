<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReworkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('rework_orders.create');
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'in:technician_error,material_defect,customer_change,incorrect_measurement,equipment_issue,unknown,other'],
            'reason' => ['required', 'string', 'max:5000'],
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'defective_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'defective_roll_id' => ['nullable', 'integer', 'exists:inventory_rolls,id'],
            'defective_batch_number' => ['nullable', 'string', 'max:255'],
            'is_customer_chargeable' => ['sometimes', 'boolean'],
            'customer_charge_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
