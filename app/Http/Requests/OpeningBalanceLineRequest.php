<?php

namespace App\Http\Requests;

class OpeningBalanceLineRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'account_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'],
            'cost_center_id' => ['nullable', 'integer'], 'currency_id' => ['required', 'integer'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'], 'debit_amount' => ['nullable', 'numeric', 'min:0'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'], 'customer_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'],
            'vehicle_id' => ['nullable', 'integer'], 'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
