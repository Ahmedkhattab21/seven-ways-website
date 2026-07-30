<?php

namespace App\Http\Requests;

class CashBoxActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب الإجراء مطلوب.',
            'reason.min' => 'يجب ألا يقل سبب الإجراء عن 5 أحرف.',
        ];
    }
}
