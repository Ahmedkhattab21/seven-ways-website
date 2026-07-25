<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SupplierAddressRequest;
use App\Http\Requests\SupplierContactRequest;
use App\Http\Requests\SupplierProductRequest;
use App\Http\Requests\SupplierRequest;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\SupplierProductService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', Supplier::class);

        return view('suppliers.index', ['suppliers' => Supplier::where('company_id', $tenant->companyId())->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier, 'currencies' => Currency::where('is_active', true)->get()]);
    }

    public function store(SupplierRequest $request, SupplierService $service): RedirectResponse
    {
        $this->authorize('create', Supplier::class);
        $supplier = $service->create($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier, TenantContext $tenant): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier->load(['contacts', 'addresses', 'products.product', 'currency']),
            'products' => Product::where('company_id', $tenant->companyId())->where('is_purchasable', true)->where('is_active', true)->get(),
            'units' => Unit::where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', ['supplier' => $supplier, 'currencies' => Currency::where('is_active', true)->get()]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $service->update($supplier, $request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function status(Supplier $supplier, string $status, SupplierService $service): RedirectResponse
    {
        $this->authorize('disable', $supplier);
        $service->setStatus($supplier, $status);

        return back()->with('success', 'Supplier status updated.');
    }

    public function contact(SupplierContactRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $service->contact($supplier, $request->validated());

        return back()->with('success', 'Supplier contact saved.');
    }

    public function address(SupplierAddressRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $service->address($supplier, $request->validated());

        return back()->with('success', 'Supplier address saved.');
    }

    public function product(SupplierProductRequest $request, Supplier $supplier, SupplierProductService $service): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $service->save($supplier, $request->validated());

        return back()->with('success', 'Supplier product saved.');
    }
}
