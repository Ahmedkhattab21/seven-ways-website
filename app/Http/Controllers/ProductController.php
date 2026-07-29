<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $products = Product::query()->where('company_id', $tenant->companyId())->with(['category', 'brand', 'stockUnit', 'saleUnit', 'defaultTax'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('sku', 'like', '%'.$request->search.'%')))
            ->when($request->filled('tracking_type'), fn ($q) => $q->where('tracking_type', $request->tracking_type))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('is_sellable'), fn ($q) => $q->where('is_sellable', $request->boolean('is_sellable')))
            ->latest()->paginate(20)->withQueryString();

        return view('inventory.products.index', [
            'products' => $products,
            'categories' => ProductCategory::where('company_id', $tenant->companyId())->orderBy('name')->get(),
            'brands' => ProductBrand::where('company_id', $tenant->companyId())->orderBy('name')->get(),
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        return view('inventory.products.form', $this->references($tenant) + ['product' => new Product]);
    }

    public function store(Request $request, ProductService $service): RedirectResponse
    {
        $product = $service->create($this->validated($request));

        return redirect()->route('products.index')->with('success', "تم إنشاء المنتج {$product->name}.");
    }

    public function edit(Product $product, TenantContext $tenant): View
    {
        $this->authorize('update', $product);

        return view('inventory.products.form', $this->references($tenant) + compact('product'));
    }

    public function update(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $this->authorize('update', $product);
        $service->update($product, $this->validated($request, $product));

        return redirect()->route('products.index')->with('success', 'تم تحديث المنتج.');
    }

    public function disable(Product $product): RedirectResponse
    {
        $this->authorize('disable', $product);
        $product->forceFill(['is_active' => false, 'updated_by' => auth()->id()])->save();

        return back()->with('success', 'تم تعطيل المنتج.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'category_id' => ['required', 'integer'], 'brand_id' => ['nullable', 'integer'],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products')->where('company_id', $companyId)->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('products')->where('company_id', $companyId)->ignore($product)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'product_type' => ['required', Rule::in(['ppf', 'thermal_insulation', 'tint', 'glass_protection', 'installation_material', 'consumable', 'accessory', 'tool', 'other'])],
            'tracking_type' => ['required', Rule::in(['quantity', 'roll', 'batch', 'serial'])],
            'purchase_unit_id' => ['required', 'integer'], 'stock_unit_id' => ['required', 'integer'], 'sale_unit_id' => ['required', 'integer'],
            'default_tax_id' => ['nullable', 'integer'], 'costing_method' => ['required', Rule::in(['weighted_average', 'specific', 'standard'])],
            'standard_cost' => ['nullable', 'numeric', 'min:0'], 'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'], 'maximum_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'], 'warranty_months' => ['nullable', 'integer', 'min:0'],
            'requires_warranty' => ['sometimes', 'boolean'],
            'default_warranty_film_type' => ['nullable', 'string', 'max:255'],
            'default_warranty_duration_value' => ['nullable', 'integer', 'min:1'],
            'default_warranty_duration_unit' => ['nullable', Rule::in(['days', 'months', 'years', 'lifetime'])],
            'default_warranty_application_area' => ['nullable', 'string', 'max:255'],
            'default_warranty_terms' => ['nullable', 'string', 'max:10000'],
            'default_warranty_notes' => ['nullable', 'string', 'max:5000'],
            'is_sellable' => ['boolean'], 'is_purchasable' => ['boolean'], 'is_consumable' => ['boolean'], 'is_active' => ['boolean'],
        ]);
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'categories' => ProductCategory::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'brands' => ProductBrand::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
            'taxes' => Tax::where('company_id', $tenant->companyId())->where('is_active', true)->get(),
        ];
    }
}
