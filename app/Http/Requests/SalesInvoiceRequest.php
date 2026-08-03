<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(TenantContext::class);
        $companyId = $tenant->companyId();
        $branchId = $tenant->branchId();
        $invoiceDate = $this->input('invoice_date', now()->toDateString());

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)->where('status', 'active')
            )],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)
                    ->where('customer_id', $this->integer('customer_id'))->where('status', 'active')
            )],
            'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_type' => ['nullable', 'in:fixed,percentage'], 'discount_value' => ['nullable', 'numeric', 'min:0'],
            'terms_snapshot' => ['nullable', 'string', 'max:5000'], 'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['prohibited'],
            'items.*.service_id' => ['prohibited'],
            'items.*.service_package_id' => ['prohibited'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                    ->where('company_id', $companyId)->where('is_active', true)->where('is_sellable', true)
                    ->whereNotIn('tracking_type', ['roll', 'scrap'])
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
                            ->whereDate('branch_product_prices.effective_from', '<=', $invoiceDate)
                            ->where(fn (Builder $dates) => $dates->whereNull('branch_product_prices.effective_to')
                                ->orWhereDate('branch_product_prices.effective_to', '>=', $invoiceDate))))),
            ],
            'items.*.warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(
                fn (Builder $query) => $query->where('company_id', $companyId)->where('branch_id', $branchId)
                    ->where('is_active', true)->where('is_system', false)
            )],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['prohibited'],
            'items.*.unit_id' => ['prohibited'],
            'items.*.tax_id' => ['prohibited'],
            'items.*.tax_rate' => ['prohibited'],
            'items.*.promotion_id' => ['prohibited'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'], 'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.warranty' => ['nullable', 'array'],
            'items.*.warranty.applies' => ['nullable', 'boolean'],
            'items.*.warranty.film_type' => ['nullable', 'string', 'max:255'],
            'items.*.warranty.film_code' => ['nullable', 'string', 'max:255'],
            'items.*.warranty.application_area' => ['nullable', 'string', 'max:255'],
            'items.*.warranty.start_date' => ['nullable', 'date'],
            'items.*.warranty.duration_value' => ['nullable', 'integer', 'min:1'],
            'items.*.warranty.duration_unit' => ['nullable', 'in:days,months,years,lifetime'],
            'items.*.warranty.terms' => ['nullable', 'string', 'max:10000'],
            'items.*.warranty.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
