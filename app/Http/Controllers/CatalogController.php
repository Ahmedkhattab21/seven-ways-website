<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Services\ProductPricingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, TenantContext $tenant, ProductPricingService $productPricing): View
    {
        $user = $request->user();
        $permissionNames = $user->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,name')
            ->get()
            ->flatMap->permissions
            ->pluck('name')
            ->unique();
        abort_unless($permissionNames->contains('products.view'), 403);

        $branches = $tenant->accessibleBranches();
        $branch = $branches->firstWhere('id', $request->integer('branch_id')) ?? $tenant->branch() ?? $branches->first();
        $companyId = $tenant->companyId();
        $today = now()->toDateString();

        $products = Product::query()
            ->where('company_id', $companyId)
            ->when($branch, fn ($query) => $query->whereHas('branchProducts', fn ($query) => $query
                ->where('branch_id', $branch->id)->where('is_available', true)))
            ->with([
                'branchProducts' => fn ($query) => $query->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
                    ->with('defaultSalesWarehouse:id,name'),
                'stockBalances' => fn ($query) => $query->when($branch, fn ($query) => $query->where('branch_id', $branch->id)),
            ])
            ->latest()
            ->limit(10)
            ->get();
        if ($branch) {
            $products->each(function (Product $product) use ($productPricing, $branch, $today) {
                try {
                    $product->setAttribute('resolved_branch_price', $productPricing->resolvePrice($product, $branch, $today));
                } catch (BusinessRuleException) {
                    $product->setAttribute('resolved_branch_price', null);
                }
            });
        }

        return view('catalog.index', [
            'permissionNames' => $permissionNames,
            'branches' => $branches,
            'branch' => $branch,
            'products' => $products,
            'summary' => $this->productCounts($companyId, $branch?->id, $today),
        ]);
    }

    private function productCounts(int $companyId, ?int $branchId, string $today): array
    {
        if (! $branchId) {
            return ['active' => 0, 'total' => 0, 'unpriced' => 0];
        }
        $query = BranchProduct::query()->where('company_id', $companyId)->where('branch_id', $branchId);

        return [
            'active' => (clone $query)->where('is_available', true)->where('is_sellable', true)->count(),
            'total' => (clone $query)->count(),
            'unpriced' => (clone $query)->whereDoesntHave('product.branchPrices', fn ($prices) => $prices
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($prices) => $prices->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today)))->count(),
        ];
    }
}
