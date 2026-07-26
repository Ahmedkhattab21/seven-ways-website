<?php

namespace App\Http\Requests;

class ProductAccountingMappingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'purchase_return_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'adjustment_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
