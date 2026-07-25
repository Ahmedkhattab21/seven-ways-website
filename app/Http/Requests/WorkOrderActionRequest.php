<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'materials_override' => ['nullable', 'boolean']];
    }
}
