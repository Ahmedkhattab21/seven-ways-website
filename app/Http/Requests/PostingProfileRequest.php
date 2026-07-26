<?php

namespace App\Http\Requests;

use App\Services\PostingProfileValidationService;
use Illuminate\Validation\Rule;

class PostingProfileRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(PostingProfileValidationService::SOURCE_TYPES)],
            'description' => ['nullable', 'string', 'max:5000'], 'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'is_default' => ['boolean'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.entry_side' => ['required', Rule::in(['debit', 'credit'])],
            'lines.*.account_source' => ['required', Rule::in(PostingProfileValidationService::ACCOUNT_SOURCES)],
            'lines.*.fixed_account_id' => ['nullable', 'integer'],
            'lines.*.amount_source' => ['required', Rule::in(PostingProfileValidationService::AMOUNT_SOURCES)],
            'lines.*.tax_component' => ['nullable', Rule::in(['none', 'input', 'output'])],
        ]);
    }
}
