<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchServicePackage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class QuotationPricingService
{
    public function __construct(
        private TenantContext $tenant,
        private ServicePricingService $servicePricing,
        private ServiceCostEstimator $costEstimator,
        private PromotionResolver $promotions,
        private MoneyRoundingService $rounding
    ) {
    }

    public function calculate(
        Branch $branch,
        Customer $customer,
        Vehicle $vehicle,
        array $items,
        array $header = []
    ): array {
        $this->assertScope($branch, $customer, $vehicle);
        if ($items === []) {
            throw new BusinessRuleException('Quotation must contain at least one item.');
        }
        $currencyDecimals = (int) ($header['currency_decimals'] ?? 2);
        $snapshots = collect($items)->values()->map(
            fn (array $item, int $index) => $this->priceItem($branch, $vehicle, $item, $index, $currencyDecimals)
        );
        $subtotal = $this->sum($snapshots, 'net_amount', $currencyDecimals);
        $headerDiscount = $this->discount(
            $subtotal,
            $header['discount_type'] ?? null,
            (string) ($header['discount_value'] ?? 0),
            $currencyDecimals
        );
        if (bccomp($headerDiscount, $subtotal, $currencyDecimals) === 1) {
            throw new BusinessRuleException('Quotation discount cannot exceed subtotal.');
        }
        $allocated = $this->allocateHeaderDiscount($snapshots, $headerDiscount, $subtotal, $currencyDecimals);
        $taxTotal = $this->sum($allocated, 'tax_amount', $currencyDecimals);
        $total = $this->rounding->round(bcadd(bcsub($subtotal, $headerDiscount, 8), $taxTotal, 8), $currencyDecimals);
        $materialCost = $this->nullableSum($allocated, 'estimated_material_cost', $currencyDecimals);
        $wasteCost = $this->nullableSum($allocated, 'estimated_waste_cost', $currencyDecimals);
        $totalCost = $this->nullableSum($allocated, 'estimated_total_cost', $currencyDecimals);
        $margin = $totalCost === null ? null : $this->rounding->round(bcsub($total, $totalCost, 8), $currencyDecimals);

        return [
            'items' => $allocated->all(), 'subtotal' => $subtotal, 'discount_amount' => $headerDiscount,
            'tax_amount' => $taxTotal, 'total' => $total, 'estimated_material_cost' => $materialCost,
            'estimated_waste_cost' => $wasteCost, 'estimated_total_cost' => $totalCost,
            'estimated_margin' => $margin,
        ];
    }

    private function priceItem(Branch $branch, Vehicle $vehicle, array $item, int $index, int $decimals): array
    {
        $type = $item['item_type'];
        $quantity = (string) $item['quantity'];
        if (bccomp($quantity, '0', 6) !== 1) {
            throw new BusinessRuleException('Item quantity must be greater than zero.');
        }
        $unitPrice = null;
        $minimumPrice = null;
        $duration = null;
        $taxId = null;
        $taxRate = '0';
        $description = trim((string) ($item['description'] ?? ''));
        $priceSource = 'manual';
        $cost = ['estimated_material_cost' => null, 'estimated_waste_cost' => null, 'estimated_total_cost' => null];
        $service = null;
        $package = null;
        $product = null;

        if ($type === 'service') {
            $service = Service::query()->whereKey($item['service_id'])->where('company_id', $branch->company_id)
                ->where('is_active', true)->firstOrFail();
            $resolved = $this->servicePricing->resolvePrice(
                $service, $branch, $vehicle->size, $vehicle->type, $quantity, $item['price_date'] ?? now()
            );
            $unitPrice = $resolved['unit_price'];
            $minimumPrice = $resolved['minimum_price'];
            $duration = $resolved['estimated_duration'];
            $taxId = $service->default_tax_id;
            $taxRate = $resolved['tax_rate'] ?? '0';
            $description = $description ?: $service->name;
            $priceSource = $resolved['price_source'];
            $cost = $this->costEstimator->estimate($service, $branch, $vehicle->size, $vehicle->type, $quantity);
        } elseif ($type === 'package') {
            $package = ServicePackage::query()->whereKey($item['service_package_id'])
                ->where('company_id', $branch->company_id)->where('is_active', true)->firstOrFail();
            $packagePrice = BranchServicePackage::query()->where('branch_id', $branch->id)
                ->where('service_package_id', $package->id)->where('is_available', true)
                ->where(fn ($query) => $vehicle->vehicle_size_id
                    ? $query->whereNull('vehicle_size_id')->orWhere('vehicle_size_id', $vehicle->vehicle_size_id)
                    : $query->whereNull('vehicle_size_id'))
                ->whereDate('effective_from', '<=', now())->where(fn ($query) => $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()))
                ->orderByRaw('vehicle_size_id IS NOT NULL DESC')->latest('effective_from')->first();
            if (! $packagePrice) {
                throw new BusinessRuleException('Package is not available for this branch and vehicle.');
            }
            $unitPrice = $packagePrice->price;
            $minimumPrice = $packagePrice->minimum_price;
            $duration = $package->items()->with('service')->get()->sum(fn ($row) => $row->service->default_duration_minutes * $row->quantity);
            $description = $description ?: $package->name;
            $priceSource = 'package_price';
        } elseif ($type === 'product') {
            $product = Product::query()->whereKey($item['product_id'])->where('company_id', $branch->company_id)
                ->where('is_active', true)->where('is_sellable', true)->firstOrFail();
            $unitPrice = $product->default_sale_price;
            $taxId = $product->default_tax_id;
            $taxRate = (string) ($product->defaultTax?->rate ?? 0);
            $description = $description ?: $product->name;
            $priceSource = 'branch_default';
        } elseif ($type !== 'custom') {
            throw new BusinessRuleException('Unsupported quotation item type.');
        }

        if (array_key_exists('manual_unit_price', $item) && $item['manual_unit_price'] !== null) {
            if (! $this->tenant->user()?->hasPermission('quotations.manual_price')) {
                throw new BusinessRuleException('Manual pricing requires permission.', status: 403);
            }
            $unitPrice = (string) $item['manual_unit_price'];
            $priceSource = in_array($priceSource, ['custom_quote', 'manual'], true) ? 'custom_quote' : 'manual';
        }
        $promotionId = null;
        if (! empty($item['promotion_id'])) {
            $promotion = $this->promotions->resolve($service, $package, $branch, $item['price_date'] ?? now());
            if (! $promotion || (int) $promotion->id !== (int) $item['promotion_id']) {
                throw new BusinessRuleException('Selected promotion is not eligible for this item.');
            }
            $promotionId = $promotion->id;
            $item['discount_type'] = $promotion->discount_type;
            $item['discount_value'] = $promotion->discount_value;
            $priceSource = 'promotion';
        }
        if ($unitPrice === null || bccomp((string) $unitPrice, '0', 4) === -1) {
            throw new BusinessRuleException('A valid price is required for this item.');
        }
        if ($type === 'custom' && $description === '') {
            throw new BusinessRuleException('Custom item description is required.');
        }
        $gross = $this->rounding->round(bcmul((string) $unitPrice, $quantity, 8), $decimals);
        $discount = $this->discount($gross, $item['discount_type'] ?? null, (string) ($item['discount_value'] ?? 0), $decimals);
        if (bccomp($discount, $gross, $decimals) === 1) {
            throw new BusinessRuleException('Item discount cannot exceed gross amount.');
        }
        $net = $this->rounding->round(bcsub($gross, $discount, 8), $decimals);
        $requiresApproval = $minimumPrice !== null
            && bccomp((string) $unitPrice, (string) $minimumPrice, 4) === -1
            && ! $this->tenant->user()?->hasPermission('quotations.override_minimum_price');
        if ($priceSource === 'manual' && ! $this->tenant->user()?->hasPermission('quotations.override_minimum_price')) {
            $requiresApproval = true;
        }

        return [
            'item_type' => $type, 'service_id' => $service?->id, 'service_package_id' => $package?->id,
            'product_id' => $product?->id, 'description' => $description, 'quantity' => $quantity,
            'unit_id' => $item['unit_id'] ?? $product?->sale_unit_id, 'unit_price' => $this->rounding->round($unitPrice, $decimals),
            'gross_amount' => $gross, 'discount_type' => $item['discount_type'] ?? null,
            'discount_value' => (string) ($item['discount_value'] ?? 0), 'discount_amount' => $discount,
            'net_amount' => $net, 'tax_id' => $taxId, 'tax_rate' => $taxRate, 'tax_amount' => '0.00',
            'total' => $net, 'minimum_price_snapshot' => $minimumPrice, 'estimated_duration_minutes' => $duration,
            'estimated_material_cost' => $cost['estimated_material_cost'] ?? null,
            'estimated_waste_cost' => $cost['estimated_waste_cost'] ?? null,
            'estimated_total_cost' => $cost['estimated_total_cost'] ?? null,
            'estimated_margin' => $cost['estimated_margin'] ?? null, 'price_source' => $priceSource,
            'promotion_id' => $promotionId, 'sort_order' => $index,
            'metadata' => ['requires_approval' => $requiresApproval, 'header_discount_allocation' => '0.00'],
            'materials' => $type === 'service' ? $this->materialSnapshots($cost) : [],
        ];
    }

    private function allocateHeaderDiscount(Collection $items, string $discount, string $subtotal, int $decimals): Collection
    {
        $remaining = $discount;

        return $items->values()->map(function (array $item, int $index) use ($items, $discount, $subtotal, $decimals, &$remaining) {
            $allocation = $index === $items->count() - 1
                ? $remaining
                : $this->rounding->round(bcdiv(bcmul($discount, $item['net_amount'], 8), $subtotal ?: '1', 8), $decimals);
            $remaining = $this->rounding->round(bcsub($remaining, $allocation, 8), $decimals);
            $taxable = $this->rounding->round(bcsub($item['net_amount'], $allocation, 8), $decimals);
            $tax = $this->rounding->round(bcdiv(bcmul($taxable, (string) $item['tax_rate'], 8), '100', 8), $decimals);
            $item['metadata']['header_discount_allocation'] = $allocation;
            $item['tax_amount'] = $tax;
            $item['total'] = $this->rounding->round(bcadd($taxable, $tax, 8), $decimals);

            return $item;
        });
    }

    private function discount(string $base, ?string $type, string $value, int $decimals): string
    {
        if (! $type || bccomp($value, '0', 4) !== 1) {
            return $this->rounding->round(0, $decimals);
        }
        if ($type === 'percentage' && bccomp($value, '100', 4) === 1) {
            throw new BusinessRuleException('Discount percentage cannot exceed 100.');
        }

        return $this->rounding->round(
            $type === 'percentage' ? bcdiv(bcmul($base, $value, 8), '100', 8) : $value,
            $decimals
        );
    }

    private function materialSnapshots(array $cost): array
    {
        return collect($cost['materials'] ?? [])->map(fn ($row) => [
            'product_id' => $row['product_id'], 'description' => $row['product_name'],
            'unit_id' => $row['unit_id'], 'expected_quantity' => $row['expected_quantity'],
            'expected_waste_quantity' => $row['expected_waste'], 'estimated_unit_cost' => $row['estimated_unit_cost'],
            'estimated_material_cost' => $row['estimated_cost'], 'source_type' => 'service_requirement',
            'source_id' => $row['requirement_id'],
        ])->all();
    }

    private function assertScope(Branch $branch, Customer $customer, Vehicle $vehicle): void
    {
        if ($branch->company_id !== $this->tenant->companyId() || ! $branch->is_active
            || ! $this->tenant->user()?->canAccessBranch($branch)
            || $customer->company_id !== $branch->company_id || $vehicle->company_id !== $branch->company_id
            || $vehicle->customer_id !== $customer->id) {
            throw new BusinessRuleException('Branch, customer, and vehicle must belong to the same accessible scope.', status: 403);
        }
    }

    private function sum(Collection $rows, string $key, int $decimals): string
    {
        return $this->rounding->round($rows->reduce(fn ($sum, $row) => bcadd($sum, (string) $row[$key], 8), '0'), $decimals);
    }

    private function nullableSum(Collection $rows, string $key, int $decimals): ?string
    {
        $values = $rows->pluck($key)->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : $this->rounding->round($values->reduce(fn ($sum, $value) => bcadd($sum, $value, 8), '0'), $decimals);
    }
}
