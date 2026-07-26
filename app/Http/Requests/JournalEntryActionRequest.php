<?php

namespace App\Http\Requests;

class JournalEntryActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['override_reason' => ['nullable', 'string', 'max:1000']]);
    }
}
