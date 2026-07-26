<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountMoveRequest;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\Currency;
use App\Services\ChartOfAccountsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', Account::class);
        $query = Account::query()->where('company_id', $tenant->companyId())->with(['type', 'group']);
        if (! request()->user()->hasPermission('accounting.accounts.view_sensitive')) {
            $query->where('is_control_account', false);
        }
        if ($search = request('search')) {
            $query->where(fn ($q) => $q->where('account_code', 'like', "%{$search}%")->orWhere('name_ar', 'like', "%{$search}%"));
        }
        foreach (['account_type_id', 'account_group_id', 'is_active', 'is_posting'] as $filter) {
            if (request()->filled($filter)) {
                $query->where($filter, request($filter));
            }
        }

        return view('accounting.accounts.index', [
            'accounts' => $query->orderBy('account_code')->paginate(50)->withQueryString(),
            'tree' => app(ChartOfAccountsService::class)->tree(),
            'types' => AccountType::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->get(),
            'groups' => AccountGroup::where('company_id', $tenant->companyId())->orderBy('code')->get(),
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', Account::class);

        return $this->form(new Account, $tenant);
    }

    public function store(AccountRequest $request, ChartOfAccountsService $service): RedirectResponse
    {
        $this->authorize('create', Account::class);
        $service->save(new Account, $request->validated());

        return redirect()->route('accounting.accounts.index')->with('success', 'تم إنشاء الحساب.');
    }

    public function edit(Account $account, TenantContext $tenant): View
    {
        $this->authorize('update', $account);

        return $this->form($account, $tenant);
    }

    public function update(AccountRequest $request, Account $account, ChartOfAccountsService $service): RedirectResponse
    {
        $this->authorize('update', $account);
        $service->save($account, $request->validated());

        return redirect()->route('accounting.accounts.index')->with('success', 'تم تحديث الحساب.');
    }

    public function move(AccountMoveRequest $request, Account $account, ChartOfAccountsService $service): RedirectResponse
    {
        $this->authorize('move', $account);
        $parent = $request->validated('parent_account_id')
            ? Account::whereKey($request->validated('parent_account_id'))->firstOrFail() : null;
        $service->move($account, $parent);

        return back()->with('success', 'تم نقل الحساب.');
    }

    public function disable(Account $account, ChartOfAccountsService $service): RedirectResponse
    {
        $this->authorize('disable', $account);
        $service->disable($account);

        return back()->with('success', 'تم تعطيل الحساب.');
    }

    private function form(Account $account, TenantContext $tenant): View
    {
        return view('accounting.accounts.form', [
            'account' => $account,
            'types' => AccountType::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
            'groups' => AccountGroup::where('company_id', $tenant->companyId())->where('is_active', true)->get(),
            'parents' => Account::where('company_id', $tenant->companyId())->where('is_header', true)->where('is_active', true)->get(),
            'currencies' => Currency::where('is_active', true)->get(),
        ]);
    }
}
