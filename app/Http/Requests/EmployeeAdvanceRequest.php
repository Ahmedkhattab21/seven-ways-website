<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employee_advances.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer'],
            'receivable_account_id' => ['required', 'integer'],
            'advance_type' => ['required', Rule::in(['advance', 'custody'])],
            'request_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
