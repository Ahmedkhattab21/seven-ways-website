<?php

namespace App\Http\Requests;

class BankReconciliationMatchRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'statement' => ['required', 'array', 'min:1'], 'statement.*.id' => ['required', 'integer', 'distinct'],
            'statement.*.amount' => ['required', 'numeric', 'gt:0'],
            'book' => ['required', 'array', 'min:1'], 'book.*.id' => ['required', 'integer', 'distinct'],
            'book.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
    }
}
