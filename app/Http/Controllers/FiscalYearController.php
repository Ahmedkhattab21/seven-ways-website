<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\FiscalPeriodGenerationRequest;
use App\Http\Requests\FiscalYearActionRequest;
use App\Http\Requests\FiscalYearRequest;
use App\Models\FiscalYear;
use App\Services\FiscalPeriodGenerationService;
use App\Services\FiscalYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', FiscalYear::class);

        return view('accounting.fiscal-years.index', [
            'years' => FiscalYear::where('company_id', $tenant->companyId())->with('periods')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(FiscalYearRequest $request, FiscalYearService $service, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('create', FiscalYear::class);
        $service->save(new FiscalYear, $tenant->companyId(), $tenant->user(), $request->validated());

        return back()->with('success', 'تم إنشاء السنة المالية.');
    }

    public function generate(FiscalPeriodGenerationRequest $request, FiscalYear $fiscalYear, FiscalPeriodGenerationService $service): RedirectResponse
    {
        $this->authorize('update', $fiscalYear);
        $service->monthly($fiscalYear);

        return back()->with('success', 'تم توليد الفترات الشهرية.');
    }

    public function action(FiscalYearActionRequest $request, FiscalYear $fiscalYear, string $action, FiscalYearService $service): RedirectResponse
    {
        $this->authorize($action === 'soft_close' ? 'softClose' : $action, $fiscalYear);
        match ($action) {
            'open' => $service->open($fiscalYear, $request->user()),
            'soft_close' => $service->softClose($fiscalYear, $request->user(), $request->validated('reason', '')),
            'reopen' => $service->reopen($fiscalYear, $request->user(), $request->validated('reason', '')),
        };

        return back()->with('success', 'تم تحديث حالة السنة المالية.');
    }
}
