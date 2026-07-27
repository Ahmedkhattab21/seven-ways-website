<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'], 'status' => ['prohibited'], 'journal_entry_id' => ['prohibited'],
            'entry_date' => ['required', 'date'], 'branch_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'adjustment_type' => ['required', Rule::in(['accrual', 'deferral', 'prepayment', 'deferred_revenue', 'provision', 'inventory', 'tax', 'manual_depreciation', 'other'])],
            'supporting_reference' => ['nullable', 'string', 'max:150'],
            'reversal_policy' => ['required', Rule::in(['none', 'manual', 'scheduled', 'next_period'])],
            'scheduled_reversal_date' => ['nullable', 'required_if:reversal_policy,scheduled,next_period', 'date', 'after:entry_date'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', 'integer'],
            'lines.*.branch_id' => ['nullable', 'integer'], 'lines.*.cost_center_id' => ['nullable', 'integer'],
            'lines.*.debit_amount' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
