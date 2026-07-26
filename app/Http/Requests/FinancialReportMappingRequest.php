<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class FinancialReportMappingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'account_group_id' => ['nullable', 'integer', 'exists:account_groups,id'],
            'cash_flow_category' => ['required', Rule::in(['operating', 'investing', 'financing'])],
            'cash_flow_line' => ['required', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
