<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class CashBoxRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'gl_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(function (Builder $query): void {
                    $query->where('company_id', $this->user()->company_id)
                        ->where('is_active', true)
                        ->where('is_posting', true)
                        ->where('is_cash_account', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'over_short_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_primary' => ['sometimes', 'boolean'],
            'allows_receipts' => ['sometimes', 'boolean'],
            'allows_payments' => ['sometimes', 'boolean'],
            'requires_shift_opening' => ['sometimes', 'boolean'],
            'maximum_cash_limit' => ['nullable', 'numeric', 'min:0'],
            'minimum_cash_limit' => ['nullable', 'numeric', 'min:0'],
            'book_balance' => ['prohibited'],
        ]);
    }

    public function messages(): array
    {
        return [
            'gl_account_id.exists' => 'يجب اختيار حساب نقدية فعال من نوع حساب حركة.',
        ];
    }
}
