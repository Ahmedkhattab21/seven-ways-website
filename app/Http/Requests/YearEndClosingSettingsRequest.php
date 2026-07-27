<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YearEndClosingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'],
            'income_summary_account_id' => ['nullable', 'integer'], 'retained_earnings_account_id' => ['required', 'integer'],
            'current_year_profit_account_id' => ['nullable', 'integer'], 'opening_balance_equity_account_id' => ['nullable', 'integer'],
            'close_revenue_directly_to_retained_earnings' => ['boolean'], 'create_opening_journal' => ['boolean'],
            'auto_create_next_fiscal_year' => ['boolean'], 'auto_generate_next_periods' => ['boolean'],
            'lock_year_after_close' => ['boolean'], 'require_all_reconciliations' => ['boolean'],
            'reconciliation_tolerance' => ['numeric', 'min:0'], 'trial_balance_tolerance' => ['numeric', 'min:0'],
            'ar_reconciliation_tolerance' => ['numeric', 'min:0'], 'ap_reconciliation_tolerance' => ['numeric', 'min:0'],
            'inventory_reconciliation_tolerance' => ['numeric', 'min:0'], 'vat_reconciliation_tolerance' => ['numeric', 'min:0'],
            'cash_reconciliation_tolerance' => ['numeric', 'min:0'],
        ];
    }
}
