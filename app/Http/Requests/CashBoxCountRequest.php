<?php

namespace App\Http\Requests;

class CashBoxCountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'count_type' => ['required', 'in:opening,interim,closing,surprise'],
            'count_input_mode' => ['required', 'in:match_book,manual_total,empty'],
            'counted_total' => ['nullable', 'numeric'],
            'zero_count' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['prohibited'], 'book_total' => ['prohibited'],
            'counted_by' => ['prohibited'], 'counted_at' => ['prohibited'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mode = $this->input('count_input_mode');
            if ($mode === 'manual_total' && (! is_numeric($this->input('counted_total'))
                || (float) $this->input('counted_total') < 0.01)) {
                $validator->errors()->add('counted_total', 'Enter a positive counted total.');
            }
            if ($mode !== 'manual_total' && $this->filled('counted_total')) {
                $validator->errors()->add('counted_total', 'Manual total is only valid for manual counting.');
            }
        });
    }
}
