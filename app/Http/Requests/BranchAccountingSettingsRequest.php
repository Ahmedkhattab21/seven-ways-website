<?php

namespace App\Http\Requests;

use App\Services\BranchAccountingSettingsService;

class BranchAccountingSettingsRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        $rules = ['default_cost_center_id' => ['nullable', 'integer']];
        foreach (BranchAccountingSettingsService::ACCOUNT_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer'];
        }

        return $this->withProtected($rules);
    }
}
