<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'], 'quotation_id' => ['nullable', 'integer'], 'lead_id' => ['nullable', 'integer'],
            'customer_id' => ['required', 'integer'], 'vehicle_id' => ['required', 'integer'],
            'scheduled_start' => ['required', 'date', 'after:now'], 'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'assigned_employee_id' => ['nullable', 'integer'],
            'booking_source' => ['required', Rule::in(['quotation', 'walk_in', 'phone', 'whatsapp', 'website', 'other'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'deposit_required' => ['sometimes', 'boolean'], 'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_notes' => ['nullable', 'string', 'max:5000'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['required', 'array', 'min:1'], 'services.*.service_id' => ['required', 'integer'],
            'services.*.service_package_id' => ['nullable', 'integer'], 'services.*.description' => ['required', 'string', 'max:500'],
            'services.*.quantity' => ['required', 'numeric', 'gt:0'],
            'services.*.estimated_duration_minutes' => ['required', 'integer', 'min:1'],
            'services.*.unit_price_snapshot' => ['required', 'numeric', 'min:0'],
            'services.*.total_snapshot' => ['required', 'numeric', 'min:0'],
            'services.*.assigned_employee_id' => ['nullable', 'integer'],
        ];
    }
}
