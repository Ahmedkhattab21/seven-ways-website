<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\LeadActionRequest;
use App\Http\Requests\LeadRequest;
use App\Models\Customer;
use App\Models\CustomerSource;
use App\Models\Lead;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\LeadConversionService;
use App\Services\LeadFollowUpService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $base = Lead::query()->forUser($request->user());
        $leads = (clone $base)->with(['branch', 'assignedUser'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($inner) use ($request) {
                $search = $request->input('search');
                $inner->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('lead_number', 'like', "%{$search}%");
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()->paginate(20)->withQueryString();
        $stats = [
            'new' => (clone $base)->where('status', 'new')->count(),
            'today' => (clone $base)->whereDate('next_follow_up_at', today())->count(),
            'overdue' => (clone $base)->where('next_follow_up_at', '<', now())->whereNotIn('status', ['won', 'lost', 'cancelled'])->count(),
            'won' => (clone $base)->where('status', 'won')->count(),
            'lost' => (clone $base)->where('status', 'lost')->count(),
        ];

        return view('leads.index', compact('leads', 'stats'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('leads.form', ['lead' => new Lead(), ...$this->options($tenant)]);
    }

    public function store(LeadRequest $request, LeadService $service): RedirectResponse
    {
        $lead = $service->save(new Lead(), $request->validated());

        return redirect()->route('leads.show', $lead)->with('status', 'تم إنشاء العميل المحتمل.');
    }

    public function show(Lead $lead, TenantContext $tenant): View
    {
        $this->authorize('view', $lead);
        $lead->load(['branch', 'brand', 'model', 'source', 'assignedUser', 'followUps', 'convertedCustomer']);
        $customers = Customer::query()->forUser($tenant->user())->orderBy('name')->get();

        return view('leads.show', compact('lead', 'customers'));
    }

    public function edit(Lead $lead, TenantContext $tenant): View
    {
        $this->authorize('update', $lead);

        return view('leads.form', ['lead' => $lead, ...$this->options($tenant)]);
    }

    public function update(LeadRequest $request, Lead $lead, LeadService $service): RedirectResponse
    {
        $service->save($lead, $request->validated());

        return redirect()->route('leads.show', $lead)->with('status', 'تم تحديث العميل المحتمل.');
    }

    public function followUp(LeadActionRequest $request, Lead $lead, LeadFollowUpService $service): RedirectResponse
    {
        $service->create($lead, $request->validated());

        return back()->with('status', 'تم تسجيل المتابعة.');
    }

    public function convert(LeadActionRequest $request, Lead $lead, LeadConversionService $service): RedirectResponse
    {
        $customer = $service->convert($lead, $request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'تم تحويل العميل المحتمل.');
    }

    public function lost(LeadActionRequest $request, Lead $lead, LeadService $service): RedirectResponse
    {
        $service->markLost($lead, $request->validated('lost_reason'));

        return back()->with('status', 'تم إغلاق العميل المحتمل كخسارة.');
    }

    private function options(TenantContext $tenant): array
    {
        return [
            'brands' => VehicleBrand::query()->where('is_active', true)->get(),
            'models' => VehicleModel::query()->where('is_active', true)->get(),
            'sources' => CustomerSource::query()->where('company_id', $tenant->companyId())->where('is_active', true)->get(),
            'users' => User::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get(),
        ];
    }
}
