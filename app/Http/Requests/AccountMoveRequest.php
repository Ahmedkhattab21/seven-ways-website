<?php

namespace App\Http\Requests;

class AccountMoveRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['parent_account_id' => ['nullable', 'integer']]);
    }
}
