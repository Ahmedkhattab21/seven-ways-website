<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AccountRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'account_type_id' => ['required', 'integer'], 'account_group_id' => ['nullable', 'integer'],
            'parent_account_id' => ['nullable', 'integer'], 'account_code' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'], 'is_header' => ['required', 'boolean'],
            'is_posting' => ['required', 'boolean'], 'normal_balance' => ['nullable', Rule::in(['debit', 'credit'])],
            'contra_reason' => ['nullable', 'string', 'max:1000'], 'currency_id' => ['nullable', 'integer'],
            'allows_multi_currency' => ['boolean'], 'requires_cost_center' => ['boolean'],
            'requires_branch' => ['boolean'], 'requires_customer' => ['boolean'], 'requires_supplier' => ['boolean'],
            'requires_employee' => ['boolean'], 'requires_vehicle' => ['boolean'], 'is_control_account' => ['boolean'],
            'control_type' => ['nullable', Rule::in([
                'accounts_receivable', 'accounts_payable', 'inventory', 'vat_input', 'vat_output',
                'customer_advances', 'supplier_advances', 'employee_advances', 'fixed_assets',
                'accumulated_depreciation', 'none',
            ])],
            'is_bank_account' => ['boolean'], 'is_cash_account' => ['boolean'], 'is_inventory_account' => ['boolean'],
            'is_tax_account' => ['boolean'], 'is_active' => ['boolean'], 'allow_manual_entry' => ['boolean'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('currency_id') && ! $this->boolean('allows_multi_currency')) {
                $validator->errors()->add(
                    'allows_multi_currency',
                    'اختيار عملة محددة للحساب يتطلب تفعيل خيار متعدد العملات.'
                );
            }

            if (! $this->boolean('is_cash_account')) {
                return;
            }

            $account = $this->route('account');
            $isActive = $this->has('is_active')
                ? $this->boolean('is_active')
                : ($account instanceof Account && $account->exists ? (bool) $account->is_active : true);
            $type = AccountType::query()
                ->whereKey($this->integer('account_type_id'))
                ->where(function ($query) {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $this->user()?->company_id);
                })
                ->first();

            if (! $this->boolean('is_posting') || $this->boolean('is_header')) {
                $validator->errors()->add('is_cash_account', 'الحساب النقدي يجب أن يكون حساب حركة وليس حسابًا رئيسيًا.');
            }
            if (! $isActive) {
                $validator->errors()->add('is_cash_account', 'الحساب النقدي يجب أن يكون نشطًا.');
            }
            if ($type?->code !== 'ASSET') {
                $validator->errors()->add('is_cash_account', 'الحساب النقدي يجب أن يكون من نوع الأصول.');
            }
            if ($this->boolean('is_control_account')) {
                $validator->errors()->add('is_cash_account', 'لا يمكن تعريف الحساب النقدي كحساب رقابي.');
            }
            if ($this->boolean('is_bank_account')) {
                $validator->errors()->add('is_cash_account', 'لا يمكن تعريف الحساب نفسه كحساب نقدي وحساب بنكي.');
            }
        });
    }
}
