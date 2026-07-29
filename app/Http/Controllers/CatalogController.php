<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const VIEW_PERMISSIONS = [
        'products.view',
        'services.view',
        'service_categories.view',
        'service_packages.view',
    ];

    public function index(Request $request, TenantContext $tenant): View
    {
        $user = $request->user();
        $permissionNames = $user->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,name')
            ->get()
            ->flatMap->permissions
            ->pluck('name')
            ->unique();
        $permissions = collect(self::VIEW_PERMISSIONS)
            ->mapWithKeys(fn (string $permission) => [$permission => $permissionNames->contains($permission)]);

        abort_unless($permissions->contains(true), 403);

        $branches = $tenant->accessibleBranches();
        $branch = $branches->firstWhere('id', $request->integer('branch_id')) ?? $tenant->branch() ?? $branches->first();
        $companyId = $tenant->companyId();
        $today = now()->toDateString();

        $products = collect();
        $services = collect();
        $packages = collect();
        $serviceCategories = collect();
        $productCategories = collect();
        $productBrands = collect();

        if ($permissions['products.view']) {
            $products = Product::query()
                ->where('company_id', $companyId)
                ->with(['category:id,name', 'brand:id,name', 'saleUnit:id,name,symbol', 'defaultTax:id,name,rate'])
                ->latest()
                ->limit(10)
                ->get();
            $productCategories = ProductCategory::query()
                ->where('company_id', $companyId)
                ->withCount('products')
                ->latest()
                ->limit(10)
                ->get();
            $productBrands = ProductBrand::query()
                ->where('company_id', $companyId)
                ->withCount('products')
                ->latest()
                ->limit(10)
                ->get();
        }

        if ($permissions['services.view']) {
            $services = Service::query()
                ->where('company_id', $companyId)
                ->with([
                    'category:id,name',
                    'branchServices' => fn ($query) => $query->when($branch, fn ($query) => $query->where('branch_id', $branch->id)),
                    'prices' => fn ($query) => $query
                        ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
                        ->whereNull('vehicle_size_id')
                        ->whereNull('vehicle_type_id')
                        ->where('is_active', true)
                        ->whereDate('effective_from', '<=', $today)
                        ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                        ->orderByDesc('priority')
                        ->orderByDesc('effective_from'),
                ])
                ->withCount('materialRequirements')
                ->latest()
                ->limit(10)
                ->get();
        }

        if ($permissions['service_packages.view']) {
            $packages = ServicePackage::query()
                ->where('company_id', $companyId)
                ->with([
                    'items.service:id,name',
                    'branchPrices' => fn ($query) => $query
                        ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
                        ->whereNull('vehicle_size_id')
                        ->where('is_available', true)
                        ->whereDate('effective_from', '<=', $today)
                        ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                        ->orderByDesc('effective_from'),
                ])
                ->withCount('items')
                ->latest()
                ->limit(10)
                ->get();
        }

        if ($permissions['service_categories.view']) {
            $serviceCategories = ServiceCategory::query()
                ->where('company_id', $companyId)
                ->withCount('services')
                ->latest()
                ->limit(10)
                ->get();
        }

        return view('catalog.index', [
            'permissions' => $permissions,
            'permissionNames' => $permissionNames,
            'branches' => $branches,
            'branch' => $branch,
            'products' => $products,
            'services' => $services,
            'packages' => $packages,
            'serviceCategories' => $serviceCategories,
            'productCategories' => $productCategories,
            'productBrands' => $productBrands,
            'summary' => [
                'products' => $permissions['products.view'] ? $this->counts(Product::class, $companyId) : null,
                'services' => $permissions['services.view'] ? $this->counts(Service::class, $companyId) : null,
                'packages' => $permissions['service_packages.view']
                    ? $this->packageCounts($companyId, $branch?->id, $today)
                    : null,
                'serviceCategories' => $permissions['service_categories.view'] ? $this->counts(ServiceCategory::class, $companyId) : null,
                'productCategories' => $permissions['products.view'] ? $this->counts(ProductCategory::class, $companyId) : null,
                'productBrands' => $permissions['products.view'] ? $this->counts(ProductBrand::class, $companyId) : null,
            ],
        ]);
    }

    private function counts(string $model, int $companyId): array
    {
        $counts = $model::query()
            ->where('company_id', $companyId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->first();

        return ['active' => (int) $counts->active, 'total' => (int) $counts->total];
    }

    private function packageCounts(int $companyId, ?int $branchId, string $today): array
    {
        $counts = $this->counts(ServicePackage::class, $companyId);
        $counts['unpriced'] = ServicePackage::query()
            ->where('company_id', $companyId)
            ->whereDoesntHave('branchPrices', fn ($query) => $query
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->where('is_available', true)
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today)))
            ->count();

        return $counts;
    }
}
