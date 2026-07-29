<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
            'deposit_decision' => ['nullable', Rule::in(['refunded', 'forfeited', 'pending_decision'])],
            'arrival_notes' => ['nullable', 'string', 'max:2000'],
            'odometer_snapshot' => ['nullable', 'integer', 'min:0'],
            'scheduled_start' => ['nullable', 'date', 'after:now'], 'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'assigned_employee_id' => ['nullable', 'integer'], 'deposit_required' => ['sometimes', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_start.date' => 'بداية الموعد غير صحيحة.',
            'scheduled_start.after' => 'يجب أن تكون بداية الموعد بعد الوقت الحالي.',
            'scheduled_end.date' => 'نهاية الموعد غير صحيحة.',
            'scheduled_end.after' => 'يجب أن تكون نهاية الموعد بعد بداية الموعد.',
        ];
    }
}
