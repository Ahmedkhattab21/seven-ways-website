<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeAdvanceSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employee_advances.settle') === true;
    }

    public function rules(): array
    {
        return [
            'settlement_type' => ['required', Rule::in(['expense_claim', 'cash_return'])],
            'settlement_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_claim_id' => ['nullable', 'required_if:settlement_type,expense_claim', 'integer'],
            'cash_receipt_id' => ['nullable', 'required_if:settlement_type,cash_return', 'integer'],
        ];
    }
}
