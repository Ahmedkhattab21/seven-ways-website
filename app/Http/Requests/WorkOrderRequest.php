<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_orders.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['appointment', 'quotation', 'direct'])],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'branch_id' => ['required_if:source,direct', 'integer', 'exists:branches,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer_id' => ['required_if:source,direct', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required_if:source,direct', 'integer', 'exists:vehicles,id'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'services' => ['required_if:source,direct', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'services.*.description' => ['required', 'string', 'max:255'],
            'services.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'services.*.planned_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'services.*.unit_price_snapshot' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
