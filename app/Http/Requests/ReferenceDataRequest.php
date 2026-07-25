<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferenceDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');
        if (in_array($section, ['currencies', 'vehicle-brands', 'vehicle-models'], true)) {
            return $this->user()->hasRole('system_admin');
        }

        return $this->user()->hasRole('system_admin')
            || $this->user()->hasPermission($this->permission($section).'.manage');
    }

    public function rules(): array
    {
        $section = $this->route('section');
        $id = $this->route('reference');
        $companyId = app(TenantContext::class)->companyId();
        $active = ['is_active' => ['nullable', 'boolean']];
        $companyCode = [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique(str_replace('-', '_', $section))->where('company_id', $companyId)->ignore($id),
            ],
        ];

        return match ($section) {
            'currencies' => [
                'code' => ['required', 'string', 'size:3', Rule::unique('currencies')->ignore($id)],
                'name_ar' => ['required', 'string', 'max:255'],
                'name_en' => ['required', 'string', 'max:255'],
                'symbol' => ['required', 'string', 'max:10'],
                'decimal_places' => ['required', 'integer', 'between:0,6'],
                ...$active,
            ],
            'taxes' => [
                ...$companyCode, 'name' => ['required', 'string', 'max:255'],
                'rate' => ['required', 'numeric', 'between:0,100'],
                'tax_type' => ['required', Rule::in(['sales', 'purchase', 'both', 'zero_rated', 'exempt'])],
                'is_default' => ['nullable', 'boolean'], 'is_inclusive' => ['nullable', 'boolean'],
                'effective_from' => ['nullable', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                ...$active,
            ],
            'units' => [
                ...$companyCode, 'name' => ['required', 'string', 'max:255'],
                'symbol' => ['required', 'string', 'max:20'],
                'unit_type' => ['required', Rule::in(['quantity', 'length', 'area', 'volume', 'weight', 'package'])],
                'decimal_places' => ['required', 'integer', 'between:0,6'], ...$active,
            ],
            'payment-methods' => [
                ...$companyCode, 'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'online', 'credit', 'other'])],
                'requires_reference' => ['nullable', 'boolean'], 'is_cash' => ['nullable', 'boolean'],
                'sort_order' => ['required', 'integer', 'between:0,65535'], ...$active,
            ],
            'vehicle-brands' => [
                'name_ar' => ['required', 'string', 'max:255'],
                'name_en' => ['nullable', 'string', 'max:255'],
                'country_code' => ['nullable', 'string', 'size:2'], ...$active,
            ],
            'vehicle-models' => [
                'vehicle_brand_id' => ['required', 'exists:vehicle_brands,id'],
                'name_ar' => ['required', 'string', 'max:255'],
                'name_en' => ['nullable', 'string', 'max:255'],
                'start_year' => ['nullable', 'integer', 'between:1900,2200'],
                'end_year' => ['nullable', 'integer', 'gte:start_year', 'between:1900,2200'], ...$active,
            ],
            'vehicle-sizes', 'vehicle-types' => [
                ...$companyCode, 'name' => ['required', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'between:0,65535'], ...$active,
            ],
            'fiscal-years' => [
                'name' => ['required', 'string', 'max:255'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
                'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
                'is_current' => ['nullable', 'boolean'],
            ],
            'document-sequences' => [
                'branch_id' => [
                    'nullable',
                    Rule::exists('branches', 'id')->where(fn ($query) => $query
                        ->where('company_id', $companyId)->where('is_active', true)),
                ],
                'document_type' => ['required', Rule::in($this->documentTypes())],
                'prefix' => ['required', 'string', 'max:100'],
                'current_number' => ['required', 'integer', 'min:0'],
                'padding' => ['required', 'integer', 'between:1,12'],
                'reset_period' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
                ...$active,
            ],
            default => [],
        };
    }

    private function permission(string $section): string
    {
        return match ($section) {
            'payment-methods' => 'payment_methods',
            'vehicle-brands', 'vehicle-models', 'vehicle-sizes', 'vehicle-types' => 'vehicle_references',
            'fiscal-years' => 'fiscal_years',
            'document-sequences' => 'document_sequences',
            default => $section,
        };
    }

    private function documentTypes(): array
    {
        return [
            'customer', 'lead', 'quotation', 'appointment', 'work_order', 'sales_invoice', 'purchase_request',
            'purchase_order', 'goods_receipt', 'purchase_invoice', 'stock_transfer',
            'receipt_voucher', 'payment_voucher', 'warranty', 'warranty_claim',
            'journal_entry', 'expense',
        ];
    }
}
