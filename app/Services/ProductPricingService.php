<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchProductPrice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ProductPricingService
{
    public function resolvePrice(
        Product $product,
        Branch $branch,
        CarbonInterface|string|null $date = null,
        ?Customer $customer = null,
        string|int|float|null $quantity = null
    ): array {
        $at = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?: now());
        if ((int) $product->company_id !== (int) $branch->company_id
            || ! $product->is_active || ! $product->is_sellable
            || ($customer && (int) $customer->company_id !== (int) $branch->company_id)
            || ($quantity !== null && (float) $quantity <= 0)) {
            throw new BusinessRuleException('المنتج أو الفرع خارج نطاق الشركة أو غير صالح للبيع.', status: 403);
        }

        $availability = BranchProduct::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->where('is_available', true)
            ->where('is_sellable', true)
            ->first();
        if (! $availability) {
            throw new BusinessRuleException('المنتج غير متاح للبيع في الفرع المحدد.');
        }

        $price = BranchProductPrice::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $at->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $at->toDateString()))
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        if (! $price && $product->default_sale_price === null) {
            throw new BusinessRuleException('لا يوجد سعر فرع أو سعر موحد ساري للمنتج المحدد.');
        }

        $promotion = Promotion::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->where('start_at', '<=', $at)
            ->where('end_at', '>=', $at)
            ->where(fn ($query) => $query->whereDoesntHave('branches')
                ->orWhereHas('branches', fn ($query) => $query->whereKey($branch->id)))
            ->where(fn ($query) => $query->where('promotion_type', 'general')
                ->orWhereHas('products', fn ($query) => $query->whereKey($product->id)))
            ->orderByDesc('discount_value')
            ->orderBy('id')
            ->first();

        $basePrice = (string) ($price?->price ?? $product->default_sale_price);
        $finalPrice = $basePrice;
        $discount = '0';
        if ($promotion) {
            if ($promotion->discount_type === 'fixed_price') {
                $finalPrice = min((float) $basePrice, (float) $promotion->discount_value);
                $discount = max(0, (float) $basePrice - (float) $finalPrice);
            } elseif ($promotion->discount_type === 'percentage') {
                $discount = (float) $basePrice * ((float) $promotion->discount_value / 100);
                $finalPrice = max(0, (float) $basePrice - $discount);
            } else {
                $discount = min((float) $basePrice, (float) $promotion->discount_value);
                $finalPrice = (float) $basePrice - $discount;
            }
        }

        return [
            'branch_product_id' => $availability->id,
            'branch_product_price_id' => $price?->id,
            'warehouse_id' => $availability->default_sales_warehouse_id,
            'base_price' => number_format((float) $basePrice, 4, '.', ''),
            'minimum_price' => $price?->minimum_price,
            'promotion_id' => $promotion?->id,
            'promotion_name' => $promotion?->name,
            'discount_amount' => number_format((float) $discount, 4, '.', ''),
            'final_price' => number_format((float) $finalPrice, 4, '.', ''),
            'effective_from' => $price?->effective_from?->toDateString(),
            'effective_to' => $price?->effective_to?->toDateString(),
            'price_source' => $promotion
                ? 'product_promotion'
                : ($price ? 'branch_product_price' : 'unified_product_price'),
            'requires_approval' => $price?->minimum_price !== null
                && (float) $finalPrice < (float) $price->minimum_price,
        ];
    }
}
