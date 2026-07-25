<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_orders.assign_technicians') ?? false;
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'role' => ['required', Rule::in(['lead', 'technician', 'assistant', 'reviewer'])], 'is_primary' => ['nullable', 'boolean'], 'hourly_cost_snapshot' => ['nullable', 'numeric', 'min:0']];
    }
}
