<?php

namespace App\Http\Requests;

class AccountingPostingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'posting_profile_id' => ['nullable', 'integer', 'exists:posting_profiles,id'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
