<?php

namespace App\Http\Requests;

use App\Services\PostingProfileValidationService;
use Illuminate\Validation\Rule;

class PostingProfileLineRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'line_number' => ['nullable', 'integer', 'min:1'], 'entry_side' => ['required', Rule::in(['debit', 'credit'])],
            'account_source' => ['required', Rule::in(PostingProfileValidationService::ACCOUNT_SOURCES)],
            'fixed_account_id' => ['nullable', 'integer'],
            'amount_source' => ['required', Rule::in(PostingProfileValidationService::AMOUNT_SOURCES)],
            'description_template' => ['nullable', 'string', 'max:255'],
            'requires_customer' => ['boolean'], 'requires_supplier' => ['boolean'],
            'requires_product' => ['boolean'], 'requires_branch' => ['boolean'],
            'requires_cost_center' => ['boolean'], 'tax_component' => ['required', Rule::in(['none', 'input', 'output'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
