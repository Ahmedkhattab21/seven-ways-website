<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServicePackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PromotionResolver
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function resolve(
        ?Service $service = null,
        ?ServicePackage $package = null,
        ?Branch $branch = null,
        CarbonInterface|string|null $date = null,
        ?Product $product = null
    ): ?Promotion {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?: now());

        return Promotion::query()->where('company_id', $this->tenant->companyId())->where('is_active', true)
            ->where('start_at', '<=', $date)->where('end_at', '>=', $date)
            ->when($branch, fn ($q) => $q->where(fn ($q) => $q->whereDoesntHave('branches')->orWhereHas('branches', fn ($q) => $q->whereKey($branch->id))))
            ->when($service, fn ($q) => $q->where(fn ($q) => $q->where('promotion_type', 'general')
                ->orWhereHas('services', fn ($q) => $q->whereKey($service->id))))
            ->when($package, fn ($q) => $q->where(fn ($q) => $q->where('promotion_type', 'general')
                ->orWhereHas('packages', fn ($q) => $q->whereKey($package->id))))
            ->when($product, fn ($q) => $q->where(fn ($q) => $q->where('promotion_type', 'general')
                ->orWhereHas('products', fn ($q) => $q->whereKey($product->id))))
            ->orderByDesc('discount_value')->first();
    }
}
