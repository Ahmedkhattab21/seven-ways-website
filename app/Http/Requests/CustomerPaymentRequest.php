<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $branchId = app(TenantContext::class)->branchId();
        $cashBoxId = $this->integer('cash_box_id');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'], 'reference_number' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'in:manual,bank_transfer,cash,card,other'], 'notes' => ['nullable', 'string', 'max:2000'],
            'cash_box_id' => ['nullable', 'integer', Rule::exists('cash_boxes', 'id')->where('company_id', $companyId)->where('branch_id', $branchId)],
            'cash_box_session_id' => ['nullable', 'integer', Rule::exists('cash_box_sessions', 'id')->where('company_id', $companyId)->where('branch_id', $branchId)->where('cash_box_id', $cashBoxId)],
            'sales_invoice_id' => ['nullable', 'integer', Rule::exists('sales_invoices', 'id')->where('company_id', $companyId)->where('branch_id', $branchId)],
            'allocation_amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('allocation_amount') && $this->has('allocated_amount')) {
            $this->merge(['allocation_amount' => $this->input('allocated_amount')]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = PaymentMethod::query()
                ->where('company_id', $this->user()?->company_id)
                ->where('is_active', true)
                ->find($this->integer('payment_method_id'));

            if (! $method) {
                return;
            }

            if ($method->isCash()) {
                foreach ([
                    'cash_box_id' => 'الخزينة مطلوبة عند اختيار الدفع النقدي.',
                    'cash_box_session_id' => 'جلسة الخزينة مطلوبة عند اختيار الدفع النقدي.',
                    'sales_invoice_id' => 'الفاتورة مطلوبة عند اختيار الدفع النقدي.',
                    'allocation_amount' => 'المبلغ المخصص مطلوب عند اختيار الدفع النقدي.',
                ] as $field => $message) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, $message);
                    }
                }

                return;
            }

            foreach (['cash_box_id', 'cash_box_session_id'] as $field) {
                if ($this->filled($field)) {
                    $validator->errors()->add($field, 'لا تُستخدم الخزينة أو جلستها مع طرق الدفع غير النقدية.');
                }
            }
        });
    }
}
