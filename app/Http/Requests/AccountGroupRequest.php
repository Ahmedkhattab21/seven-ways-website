<?php

namespace App\Http\Requests;

class AccountGroupRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'account_type_id' => ['required', 'integer'], 'parent_group_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:50'], 'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
