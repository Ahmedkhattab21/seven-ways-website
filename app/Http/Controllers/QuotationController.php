<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\QuotationRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Vehicle;
use App\Services\QuotationPrintService;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $quotations = Quotation::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->with(['branch', 'customer', 'vehicle', 'items'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->customer_id))
            ->when($request->boolean('expired'), fn ($query) => $query->whereDate('valid_until', '<', today()))
            ->latest()->paginate(20)->withQueryString();

        return view('quotations.index', ['quotations' => $quotations, 'branches' => $tenant->accessibleBranches()]);
    }

    public function create(Request $request, TenantContext $tenant): View
    {
        return view('quotations.form', ['quotation' => new Quotation, 'leadId' => $request->integer('lead_id')] + $this->references($tenant));
    }

    public function store(QuotationRequest $request, QuotationService $service): RedirectResponse
    {
        $quotation = $service->save($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('quotations.show', $quotation)->with('success', 'تم إنشاء عرض السعر.');
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);
        $quotation->load(['branch', 'customer', 'vehicle.brand', 'vehicle.model', 'items.materials', 'appointments', 'parent', 'versions']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation, TenantContext $tenant): View
    {
        $this->authorize('update', $quotation);
        $quotation->load('items');

        return view('quotations.form', ['quotation' => $quotation, 'leadId' => $quotation->lead_id] + $this->references($tenant));
    }

    public function update(QuotationRequest $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $service->save($request->safe()->except('items'), $request->validated('items'), $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'تم تحديث عرض السعر.');
    }

    public function print(Quotation $quotation, QuotationPrintService $print): View
    {
        $this->authorize('print', $quotation);

        return view('quotations.print', ['quotation' => $print->prepare($quotation)]);
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'branches' => $tenant->accessibleBranches(),
            'customers' => Customer::query()->where('company_id', $tenant->companyId())->where('status', 'active')->orderBy('name')->get(),
            'vehicles' => Vehicle::query()->where('company_id', $tenant->companyId())->where('status', 'active')->orderByDesc('id')->get(),
            'services' => Service::query()->where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::query()->where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $tenant->companyId())->where('is_active', true)->where('is_sellable', true)->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ];
    }
}
