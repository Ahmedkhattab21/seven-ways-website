<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankAccountActionRequest;
use App\Http\Requests\BankAccountBranchAccessRequest;
use App\Http\Requests\BankAccountRequest;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Services\BankAccountAccessService;
use App\Services\BankAccountService;
use App\Services\TreasuryBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(TenantContext $tenant, TreasuryBalanceService $balances): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.bank_accounts.view'), 403);
        $accounts = BankAccount::query()->where('company_id', $tenant->companyId())
            ->with(['bank', 'branch', 'currency', 'glAccount', 'branchAccess'])->orderBy('account_code')->get()
            ->filter(fn ($account) => $tenant->user()->can('view', $account))->values();

        return view('treasury.bank-accounts', [
            'bankAccounts' => $accounts,
            'balances' => $accounts->mapWithKeys(fn ($account) => [$account->id => $balances->bank($account)]),
            'showSensitive' => $tenant->user()->hasPermission('treasury.bank_accounts.view_sensitive'),
            'banks' => Bank::query()->where(fn ($query) => $query->whereNull('company_id')
                ->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
            'branches' => $tenant->accessibleBranches(),
            'currencies' => Currency::query()->where('is_active', true)->get(),
            'glAccounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)->where('is_posting', true)->get(),
        ]);
    }

    public function store(BankAccountRequest $request, BankAccountService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.bank_accounts.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء الحساب البنكي كمسودة.');
    }

    public function update(BankAccountRequest $request, BankAccount $bankAccount, BankAccountService $service): RedirectResponse
    {
        $this->authorize('update', $bankAccount);
        $service->update($bankAccount, $request->validated());

        return back()->with('success', 'تم تحديث الحساب البنكي.');
    }

    public function action(
        BankAccountActionRequest $request,
        BankAccount $bankAccount,
        string $action,
        BankAccountService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_accounts.'.$action), 403);
        $service->action($bankAccount, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث حالة الحساب البنكي.');
    }

    public function access(
        BankAccountBranchAccessRequest $request,
        BankAccount $bankAccount,
        BankAccountAccessService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_accounts.manage_branch_access'), 403);
        $service->save($bankAccount, $request->validated());

        return back()->with('success', 'تم تحديث صلاحية الفرع.');
    }
}
