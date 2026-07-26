<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountGroupRequest;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Services\AccountGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountGroupController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', AccountGroup::class);

        return view('accounting.groups.index', [
            'groups' => AccountGroup::where('company_id', $tenant->companyId())->with(['type', 'parent'])->orderBy('path')->get(),
            'types' => AccountType::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->get(),
        ]);
    }

    public function store(AccountGroupRequest $request, AccountGroupService $service): RedirectResponse
    {
        $this->authorize('create', AccountGroup::class);
        $service->save(new AccountGroup, $request->validated());

        return back()->with('success', 'تم إنشاء مجموعة الحسابات.');
    }

    public function update(AccountGroupRequest $request, AccountGroup $accountGroup, AccountGroupService $service): RedirectResponse
    {
        $this->authorize('update', $accountGroup);
        $service->save($accountGroup, $request->validated());

        return back()->with('success', 'تم تحديث المجموعة.');
    }

    public function disable(AccountGroup $accountGroup, AccountGroupService $service): RedirectResponse
    {
        $this->authorize('disable', $accountGroup);
        $service->disable($accountGroup);

        return back()->with('success', 'تم تعطيل المجموعة.');
    }
}
