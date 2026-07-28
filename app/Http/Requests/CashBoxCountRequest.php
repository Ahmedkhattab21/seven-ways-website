<?php

namespace App\Http\Requests;

class CashBoxCountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'count_type' => ['required', 'in:opening,interim,closing,surprise'],
            'zero_count' => ['sometimes', 'boolean'],
            'counted_total' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*.denomination' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'],
            'lines.*.line_total' => ['prohibited'], 'book_total' => ['prohibited'],
            'counted_by' => ['prohibited'], 'counted_at' => ['prohibited'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $zero = $this->boolean('zero_count');
            $lines = $this->input('lines', []);
            if ($zero && ! empty($lines)) {
                $validator->errors()->add('lines', 'Zero count cannot include denomination lines.');
            }
            if (! $zero && empty($lines)) {
                $validator->errors()->add('lines', 'Add denomination lines or select zero cash count.');
            }
        });
    }
}
