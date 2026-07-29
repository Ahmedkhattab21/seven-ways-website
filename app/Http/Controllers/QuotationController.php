<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\QuotationRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Vehicle;
use App\Services\QuotationPricingService;
use App\Services\QuotationPrintService;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
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
        $leadId = $request->filled('lead_id') && $request->integer('lead_id') > 0
            ? $request->integer('lead_id')
            : null;

        return view('quotations.form', ['quotation' => new Quotation, 'leadId' => $leadId] + $this->references($tenant));
    }

    public function store(QuotationRequest $request, QuotationService $service): RedirectResponse
    {
        $quotation = $service->save($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('quotations.show', $quotation)->with('success', 'تم إنشاء عرض السعر.');
    }

    public function preview(
        QuotationRequest $request,
        TenantContext $tenant,
        QuotationPricingService $pricing
    ): JsonResponse {
        $data = $request->validated();
        $branch = $tenant->accessibleBranches()->firstWhere('id', (int) $data['branch_id']);
        abort_unless($branch, 403);

        $customer = Customer::query()->whereKey($data['customer_id'])
            ->where('company_id', $tenant->companyId())->firstOrFail();
        $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])
            ->where('company_id', $tenant->companyId())->where('customer_id', $customer->id)
            ->with(['size', 'type'])->firstOrFail();
        $currency = Currency::query()->whereKey($data['currency_id'])->where('is_active', true)->firstOrFail();
        $items = collect($data['items'])->map(fn (array $item) => $item + [
            'price_date' => $data['quotation_date'],
        ])->all();
        if (! empty($data['price_includes_tax'])) {
            throw new BusinessRuleException('Current service catalog prices are tax-exclusive.');
        }
        try {
            $calculated = $pricing->calculate($branch, $customer, $vehicle, $items, [
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'currency_decimals' => $currency->decimal_places,
            ]);
        } catch (BusinessRuleException $exception) {
            return response()->json([
                'message' => match (true) {
                    str_contains($exception->getMessage(), 'price'),
                    str_contains($exception->getMessage(), 'available') => 'لا يوجد سعر فعال لهذا العنصر في الفرع المحدد ولبيانات السيارة الحالية.',
                    str_contains($exception->getMessage(), 'discount') => 'قيمة الخصم غير صالحة أو تتجاوز المبلغ المتاح.',
                    default => 'تعذر حساب عرض السعر بالبيانات الحالية.',
                },
                'errors' => $exception->errors(),
            ], $exception->status());
        }

        $canViewMinimumPrice = (bool) $tenant->user()?->hasPermission('quotations.override_minimum_price');
        $canViewCost = (bool) $tenant->user()?->hasPermission('quotations.view_cost');
        $previewItems = collect($calculated['items'])->map(function (array $item) use ($canViewMinimumPrice) {
            $requiresApproval = (bool) ($item['metadata']['requires_approval'] ?? false);
            $preview = [
                'item_type' => $item['item_type'],
                'description' => $item['description'],
                'base_unit_price' => $item['metadata']['base_unit_price'] ?? $item['unit_price'],
                'unit_price' => $item['unit_price'],
                'price_source' => $item['price_source'],
                'quantity' => $item['quantity'],
                'gross_amount' => $item['gross_amount'],
                'item_discount_amount' => $item['discount_amount'],
                'net_amount' => $item['net_amount'],
                'tax_rate' => $item['tax_rate'],
                'tax_amount' => $item['tax_amount'],
                'line_total' => $item['total'],
                'estimated_duration_minutes' => $item['estimated_duration_minutes'],
                'requires_approval' => $requiresApproval,
                'warnings' => $requiresApproval
                    ? ['السعر المعدل أقل من الحد المعتمد أو يحتاج موافقة قبل الإرسال.']
                    : [],
                'package_services' => $item['metadata']['package_services'] ?? [],
                'standalone_services_total' => $item['metadata']['standalone_services_total'] ?? null,
                'package_savings' => $item['metadata']['package_savings'] ?? null,
            ];
            if ($canViewMinimumPrice) {
                $preview['minimum_price'] = $item['minimum_price_snapshot'];
            }

            return $preview;
        });
        $subtotalBeforeDiscounts = $previewItems->reduce(
            fn (string $sum, array $item) => bcadd($sum, (string) $item['gross_amount'], 8),
            '0'
        );
        $itemDiscounts = $previewItems->reduce(
            fn (string $sum, array $item) => bcadd($sum, (string) $item['item_discount_amount'], 8),
            '0'
        );

        $summary = [
            'subtotal_before_discounts' => number_format((float) $subtotalBeforeDiscounts, $currency->decimal_places, '.', ''),
            'item_discounts_total' => number_format((float) $itemDiscounts, $currency->decimal_places, '.', ''),
            'subtotal_after_item_discounts' => $calculated['subtotal'],
            'header_discount_amount' => $calculated['discount_amount'],
            'tax_amount' => $calculated['tax_amount'],
            'grand_total' => $calculated['total'],
            'item_count' => $previewItems->count(),
            'estimated_duration_minutes' => $previewItems->sum('estimated_duration_minutes'),
            'currency_code' => $currency->code,
        ];
        if ($canViewCost) {
            $summary['estimated_total_cost'] = $calculated['estimated_total_cost'];
            $summary['estimated_margin'] = $calculated['estimated_margin'];
        }

        return response()->json([
            'items' => $previewItems->values(),
            'summary' => $summary,
        ]);
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);
        $quotation->load(['branch', 'customer', 'currency', 'vehicle.brand', 'vehicle.model', 'items.materials', 'appointments', 'parent', 'versions']);

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
        $branchIds = $tenant->accessibleBranches()->pluck('id');

        return [
            'branches' => $tenant->accessibleBranches(),
            'customers' => Customer::query()->where('company_id', $tenant->companyId())->where('status', 'active')->orderBy('name')->get(),
            'vehicles' => Vehicle::query()->where('company_id', $tenant->companyId())->where('status', 'active')
                ->with(['customer', 'brand', 'model', 'size'])->orderByDesc('id')->get(),
            'services' => Service::query()->where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::query()->where('company_id', $tenant->companyId())->where('is_active', true)
                ->whereHas('branchPrices', fn ($query) => $query->whereIn('branch_id', $branchIds)
                    ->where('is_available', true))
                ->with([
                    'items.service:id,name,default_duration_minutes',
                    'branchPrices' => fn ($query) => $query->whereIn('branch_id', $branchIds)
                        ->where('is_available', true),
                ])->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $tenant->companyId())->where('is_active', true)->where('is_sellable', true)->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'companyCurrencyId' => $tenant->company()?->currency_id,
        ];
    }
}
