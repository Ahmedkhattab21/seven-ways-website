<?php

namespace App\Http\Requests;

class BankRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'code' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_system' => ['prohibited'],
        ]);
    }
}
