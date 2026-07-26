<?php

namespace App\Http\Requests;

class AccountingSettingsRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'base_currency_id' => ['required', 'integer'], 'current_fiscal_year_id' => ['nullable', 'integer'],
            'default_rounding_precision' => ['required', 'integer', 'between:0,8'],
            'allow_manual_journals' => ['boolean'], 'require_journal_approval' => ['boolean'],
            'enforce_balanced_dimensions' => ['boolean'], 'enforce_cost_center_on_expense' => ['boolean'],
            'enforce_branch_on_posting' => ['boolean'], 'allow_posting_to_soft_closed_period' => ['boolean'],
            'separation_of_duties' => ['boolean'],
            'auto_post_sales' => ['prohibited'], 'auto_post_purchases' => ['prohibited'],
            'auto_post_inventory' => ['prohibited'], 'auto_post_payments' => ['prohibited'],
        ]);
    }
}
