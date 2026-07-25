<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CustomerRelatedRequest;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSource;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $customers = Customer::query()->forUser($request->user())
            ->with(['assignedBranch', 'source'])->withCount('vehicles')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('customer_type', $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('assigned_branch_id', $request->integer('branch_id')))
            ->when($request->filled('source_id'), fn ($query) => $query->where('source_id', $request->integer('source_id')))
            ->latest()->paginate(20)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'branches' => $tenant->accessibleBranches(),
            'sources' => CustomerSource::query()->where('company_id', $tenant->companyId())->where('is_active', true)->get(),
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        return view('customers.form', ['customer' => new Customer(), ...$this->options($tenant)]);
    }

    public function store(CustomerRequest $request, CustomerService $service): RedirectResponse
    {
        $customer = $service->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'تم إنشاء العميل.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);
        $user = auth()->user();
        $customer->load([
            'assignedBranch', 'source', 'contacts', 'addresses', 'vehicles.brand', 'vehicles.model',
            'attachments', 'notes' => fn ($query) => $query->where(function ($inner) use ($user) {
                $inner->where('visibility', 'company')
                    ->orWhere(fn ($branch) => $branch->where('visibility', 'branch')->where('branch_id', app(TenantContext::class)->branchId()))
                    ->orWhere(fn ($private) => $private->where('visibility', 'private')
                        ->when(! $user->isCompanyAdministrator(), fn ($own) => $own->where('created_by', $user->id)));
            }),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer, TenantContext $tenant): View
    {
        $this->authorize('update', $customer);

        return view('customers.form', ['customer' => $customer, ...$this->options($tenant)]);
    }

    public function update(CustomerRequest $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        $service->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'تم تحديث العميل.');
    }

    public function disable(Customer $customer, CustomerService $service): RedirectResponse
    {
        $this->authorize('disable', $customer);
        $service->disable($customer);

        return back()->with('status', 'تم تعطيل العميل.');
    }

    public function storeContact(CustomerRelatedRequest $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        $data = $request->validated();
        $data['is_primary'] = $request->boolean('is_primary');
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $service->addContact($customer, $data);

        return back()->with('status', 'تمت إضافة جهة الاتصال.');
    }

    public function storeAddress(CustomerRelatedRequest $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $service->addAddress($customer, $data);

        return back()->with('status', 'تمت إضافة العنوان.');
    }

    public function destroyContact(CustomerContact $contact, CustomerService $service): RedirectResponse
    {
        $this->authorize('view', $contact->customer);
        abort_unless(auth()->user()->hasPermission('customers.manage_contacts'), 403);
        $service->deleteContact($contact);

        return back()->with('status', 'تم حذف جهة الاتصال.');
    }

    public function storeNote(CustomerRelatedRequest $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        $service->addNote($customer, $request->validated());

        return back()->with('status', 'تمت إضافة الملاحظة.');
    }

    private function options(TenantContext $tenant): array
    {
        return [
            'branches' => $tenant->accessibleBranches(),
            'sources' => CustomerSource::query()->where('company_id', $tenant->companyId())->where('is_active', true)->get(),
        ];
    }
}
