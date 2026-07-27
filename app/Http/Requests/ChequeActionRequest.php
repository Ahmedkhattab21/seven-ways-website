<?php

namespace App\Http\Requests;

class ChequeActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'replacement_cheque_number' => ['nullable', 'string', 'max:100'],
            'replacement_issue_date' => ['nullable', 'date'],
            'replacement_due_date' => ['nullable', 'date'],
        ]);
    }
}
