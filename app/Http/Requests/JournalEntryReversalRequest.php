<?php

namespace App\Http\Requests;

class JournalEntryReversalRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'reason' => ['required', 'string', 'max:2000'],
            'posting_date' => ['nullable', 'date'],
        ]);
    }
}
