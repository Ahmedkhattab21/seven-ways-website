<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountingPeriodActionRequest;
use App\Http\Requests\AccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', AccountingPeriod::class);

        return view('accounting.periods.index', [
            'periods' => AccountingPeriod::where('company_id', $tenant->companyId())->with('fiscalYear')->orderByDesc('start_date')->paginate(50),
            'years' => FiscalYear::where('company_id', $tenant->companyId())->get(),
        ]);
    }

    public function store(AccountingPeriodRequest $request, AccountingPeriodService $service): RedirectResponse
    {
        $this->authorize('create', AccountingPeriod::class);
        $service->save(new AccountingPeriod, $request->validated());

        return back()->with('success', 'تم إنشاء الفترة.');
    }

    public function action(AccountingPeriodActionRequest $request, AccountingPeriod $accountingPeriod, string $action, AccountingPeriodService $service): RedirectResponse
    {
        $permission = $action === 'open' ? 'reopen' : $action;
        abort_unless($request->user()->hasPermission('accounting.periods.'.$permission), 403);
        $service->transition($accountingPeriod, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث حالة الفترة.');
    }
}
