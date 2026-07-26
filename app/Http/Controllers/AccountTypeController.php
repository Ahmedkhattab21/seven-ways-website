<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountTypeRequest;
use App\Models\AccountType;
use App\Services\AccountTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountTypeController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', AccountType::class);

        return view('accounting.account-types.index', [
            'types' => AccountType::query()
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function store(AccountTypeRequest $request, AccountTypeService $service): RedirectResponse
    {
        $this->authorize('create', AccountType::class);
        $service->save(new AccountType, $request->validated());

        return back()->with('success', 'تم إنشاء نوع الحساب.');
    }

    public function update(AccountTypeRequest $request, AccountType $accountType, AccountTypeService $service): RedirectResponse
    {
        $this->authorize('update', $accountType);
        $service->save($accountType, $request->validated());

        return back()->with('success', 'تم تحديث نوع الحساب.');
    }
}
