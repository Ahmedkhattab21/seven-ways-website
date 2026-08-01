<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
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
        return true;
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['prohibited'],
            'items.*.service_id' => ['prohibited'],
            'items.*.service_package_id' => ['prohibited'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                    ->where('company_id', $companyId)->where('is_active', true)->where('is_sellable', true)
                    ->whereExists(fn (Builder $availability) => $availability->selectRaw('1')
                        ->from('branch_products')
                        ->whereColumn('branch_products.product_id', 'products.id')
                        ->where('branch_products.company_id', $companyId)
                        ->where('branch_products.branch_id', $branchId)
                        ->where('branch_products.is_available', true)
                        ->where('branch_products.is_sellable', true))
                    ->where(fn (Builder $prices) => $prices->whereNotNull('products.default_sale_price')
                        ->orWhereExists(fn (Builder $branchPrice) => $branchPrice->selectRaw('1')
                            ->from('branch_product_prices')
                            ->whereColumn('branch_product_prices.product_id', 'products.id')
                            ->where('branch_product_prices.company_id', $companyId)
                            ->where('branch_product_prices.branch_id', $branchId)
                            ->where('branch_product_prices.is_active', true)
                            ->whereDate('branch_product_prices.effective_from', '<=', $quotationDate)
                            ->where(fn (Builder $dates) => $dates->whereNull('branch_product_prices.effective_to')
                                ->orWhereDate('branch_product_prices.effective_to', '>=', $quotationDate))))),
            ],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_id' => ['prohibited'],
            'items.*.manual_unit_price' => ['prohibited'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.promotion_id' => ['prohibited'],
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
}
