<?php

namespace App\Http\Requests;

class CashBoxCountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'count_type' => ['required', 'in:opening,interim,closing,surprise'],
            'counted_total' => ['nullable', 'numeric', 'min:0', 'required_without:lines'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_without:lines'],
            'lines' => ['nullable', 'array', 'min:1'],
            'lines.*.denomination' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'],
            'lines.*.line_total' => ['prohibited'], 'book_total' => ['prohibited'],
            'counted_by' => ['prohibited'], 'counted_at' => ['prohibited'],
        ]);
    }
}
