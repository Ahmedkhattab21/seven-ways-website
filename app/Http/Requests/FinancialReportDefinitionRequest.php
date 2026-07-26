<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class FinancialReportDefinitionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'code' => ['required', 'string', 'max:50'], 'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'report_type' => ['required', Rule::in(['balance_sheet', 'income_statement', 'cash_flow', 'custom'])],
            'is_active' => ['nullable', 'boolean'], 'is_system' => ['prohibited'],
        ]);
    }
}
