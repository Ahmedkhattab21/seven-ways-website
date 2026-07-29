<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuotationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (in_array($this->input('lead_id'), [null, '', 0, '0'], true)) {
            $this->merge(['lead_id' => null]);
        }
    }

    public function authorize(): bool
    {
        $hasManualPrice = collect($this->input('items', []))->contains(
            fn ($item) => is_array($item)
                && array_key_exists('manual_unit_price', $item)
                && $item['manual_unit_price'] !== null
                && $item['manual_unit_price'] !== ''
        );

        return ! $hasManualPrice || (bool) $this->user()?->hasPermission('quotations.manual_price');
    }

    public function rules(): array
    {
        $tenant = app(TenantContext::class);
        $companyId = $tenant->companyId();
        $branchId = $this->integer('branch_id');
        $quotationDate = $this->input('quotation_date', now()->toDateString());
        $accessibleBranchIds = $tenant->accessibleBranches()->pluck('id')->all();

        return [
            'branch_id' => ['required', 'integer', Rule::in($accessibleBranchIds), Rule::exists('branches', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)->where('is_active', true)
            )],
            'lead_id' => ['nullable', 'integer'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)->where('status', 'active')
            )],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)
                    ->where('customer_id', $this->integer('customer_id'))->where('status', 'active')
            )],
            'quotation_date' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'price_includes_tax' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['prohibited'], 'discount_amount' => ['prohibited'],
            'tax_amount' => ['prohibited'], 'total' => ['prohibited'],
            'customer_notes' => ['nullable', 'string', 'max:5000'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.item_type' => ['required', Rule::in(['service', 'package', 'product', 'custom'])],
            'items.*.service_id' => [
                'required_if:items.*.item_type,service', 'prohibited_unless:items.*.item_type,service', 'integer',
                Rule::exists('services', 'id')->where(fn (Builder $query) => $query
                    ->where('company_id', $companyId)->where('is_active', true)
                    ->whereExists(fn (Builder $branchService) => $branchService->selectRaw('1')
                        ->from('branch_services')->whereColumn('branch_services.service_id', 'services.id')
                        ->where('branch_services.branch_id', $branchId)->where('branch_services.is_active', true)
                        ->where('branch_services.is_available', true))),
            ],
            'items.*.service_package_id' => [
                'required_if:items.*.item_type,package', 'prohibited_unless:items.*.item_type,package', 'integer',
                Rule::exists('service_packages', 'id')->where(fn (Builder $query) => $query
                    ->where('company_id', $companyId)->where('is_active', true)
                    ->whereExists(fn (Builder $branchPackage) => $branchPackage->selectRaw('1')
                        ->from('branch_service_packages')
                        ->whereColumn('branch_service_packages.service_package_id', 'service_packages.id')
                        ->where('branch_service_packages.branch_id', $branchId)
                        ->where('branch_service_packages.is_available', true)
                        ->whereDate('branch_service_packages.effective_from', '<=', $quotationDate)
                        ->where(fn (Builder $dates) => $dates->whereNull('branch_service_packages.effective_to')
                            ->orWhereDate('branch_service_packages.effective_to', '>=', $quotationDate)))),
            ],
            'items.*.product_id' => [
                'required_if:items.*.item_type,product', 'prohibited_unless:items.*.item_type,product', 'integer',
                Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                    ->where('company_id', $companyId)->where('is_active', true)->where('is_sellable', true)),
            ],
            'items.*.description' => ['required_if:items.*.item_type,custom', 'nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.manual_unit_price' => ['required_if:items.*.item_type,custom', 'nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.promotion_id' => ['nullable', 'integer'],
            'items.*.unit_price' => ['prohibited'], 'items.*.gross_amount' => ['prohibited'],
            'items.*.discount_amount' => ['prohibited'], 'items.*.net_amount' => ['prohibited'],
            'items.*.tax_amount' => ['prohibited'], 'items.*.total' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('discount_type') === 'percentage' && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'نسبة الخصم الإضافي لا يمكن أن تتجاوز 100%.');
            }

            foreach ($this->input('items', []) as $index => $item) {
                if (($item['discount_type'] ?? null) === 'percentage'
                    && (float) ($item['discount_value'] ?? 0) > 100) {
                    $validator->errors()->add(
                        "items.{$index}.discount_value",
                        'نسبة خصم العنصر لا يمكن أن تتجاوز 100%.'
                    );
                }
            }
        });
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('تعديل السعر يدويًا يتطلب صلاحية quotations.manual_price.');
    }
}
