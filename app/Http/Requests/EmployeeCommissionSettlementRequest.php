<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeCommissionSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('commissions.settle') === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'settlement_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.accrual_id' => ['required', 'integer', 'distinct'],
            'lines.*.amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
