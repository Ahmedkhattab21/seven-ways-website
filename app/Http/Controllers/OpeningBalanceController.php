<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\OpeningBalanceActionRequest;
use App\Http\Requests\OpeningBalanceLineRequest;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\OpeningBalanceDocument;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', OpeningBalanceDocument::class);

        return view('accounting.opening-balances.index', [
            'documents' => OpeningBalanceDocument::where('company_id', $tenant->companyId())->with(['fiscalYear', 'lines.account'])->latest()->get(),
            'years' => FiscalYear::where('company_id', $tenant->companyId())->get(),
            'branches' => $tenant->accessibleBranches(),
            'accounts' => Account::where('company_id', $tenant->companyId())->where('is_active', true)->where('is_posting', true)->get(),
            'costCenters' => CostCenter::where('company_id', $tenant->companyId())->where('is_active', true)->where('is_posting', true)->get(),
            'currencies' => Currency::where('is_active', true)->get(),
        ]);
    }

    public function store(OpeningBalanceRequest $request, OpeningBalanceService $service): RedirectResponse
    {
        $this->authorize('create', OpeningBalanceDocument::class);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء مستند الرصيد الافتتاحي.');
    }

    public function line(OpeningBalanceLineRequest $request, OpeningBalanceDocument $openingBalance, OpeningBalanceService $service): RedirectResponse
    {
        $this->authorize('update', $openingBalance);
        $service->addLine($openingBalance, $request->validated());

        return back()->with('success', 'تمت إضافة السطر.');
    }

    public function action(OpeningBalanceActionRequest $request, OpeningBalanceDocument $openingBalance, string $action, OpeningBalanceService $service): RedirectResponse
    {
        $ability = $action === 'mark_ready' ? 'markReady' : $action;
        $this->authorize($ability, $openingBalance);
        $service->action($openingBalance, $action);

        return back()->with('success', 'تم تحديث حالة الرصيد الافتتاحي.');
    }
}
