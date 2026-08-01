<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\BranchProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchProductController extends Controller
{
    public function edit(Product $product, Branch $branch, TenantContext $tenant): View
    {
        abort_unless((int) $product->company_id === $tenant->companyId()
            && (int) $branch->company_id === $tenant->companyId()
            && $tenant->user()?->canAccessBranch($branch), 403);

        return view('inventory.products.branch-settings', [
            'product' => $product,
            'branch' => $branch,
            'branchProduct' => $product->branchProducts()->where('branch_id', $branch->id)->first(),
            'prices' => $product->branchPrices()->where('branch_id', $branch->id)
                ->orderByDesc('effective_from')->orderByDesc('priority')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $tenant->companyId())
                ->where('branch_id', $branch->id)->where('is_active', true)
                ->where('allows_sale_issue', true)->orderBy('name')->get(),
        ]);
    }

    public function update(
        Request $request,
        Product $product,
        Branch $branch,
        BranchProductService $service
    ): RedirectResponse {
        $data = $request->validate([
            'default_sales_warehouse_id' => ['nullable', 'integer'],
            'is_available' => ['required', 'boolean'],
            'is_sellable' => ['required', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'required_with:effective_from'],
            'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'effective_from' => ['nullable', 'date', 'required_with:price'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        if (! $request->user()->hasPermission('products.manage_branch_prices')) {
            $data = collect($data)->except([
                'price', 'minimum_price', 'effective_from', 'effective_to', 'priority',
            ])->all();
        }
        $service->save($product, $branch, $data, $request->user());

        return back()->with('success', 'تم تحديث إتاحة المنتج وسعر الفرع بنجاح.');
    }
}
