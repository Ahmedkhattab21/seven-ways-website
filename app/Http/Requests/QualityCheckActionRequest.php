<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QualityCheckActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('quality_checks.'.($this->route('action') === 'pass' ? 'pass' : 'fail'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => [$this->route('action') === 'fail' ? 'required' : 'nullable', 'string', 'max:5000'],
            'reason_code' => ['nullable', 'in:technician_error,material_defect,customer_change,incorrect_measurement,equipment_issue,unknown,other'],
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'required_action' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
