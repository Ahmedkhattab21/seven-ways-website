<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountingClosingActionRequest;
use App\Models\AccountingClosingRun;
use App\Models\FiscalYear;
use App\Models\YearEndClosingSetting;
use App\Services\AccountingReopenService;
use App\Services\YearEndClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class YearEndClosingController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('accounting.year_end.view'), 403);

        return view('accounting.closing.year-end', [
            'years' => FiscalYear::where('company_id', $tenant->companyId())
                ->with('periods')->orderByDesc('start_date')->get(),
            'settings' => YearEndClosingSetting::where('company_id', $tenant->companyId())->first(),
            'runs' => AccountingClosingRun::where('company_id', $tenant->companyId())
                ->whereIn('closing_type', ['year_end_close', 'reopen_year'])->latest('id')->get(),
        ]);
    }

    public function start(
        AccountingClosingActionRequest $request,
        FiscalYear $fiscalYear,
        YearEndClosingService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('accounting.year_end.start'), 403);
        $service->start($fiscalYear, $request->validated('reason'));

        return back()->with('success', 'تم بدء فحص إقفال السنة.');
    }

    public function action(
        AccountingClosingActionRequest $request,
        AccountingClosingRun $closingRun,
        string $action,
        YearEndClosingService $service
    ): RedirectResponse {
        abort_unless(in_array($action, ['review', 'approve', 'execute'], true)
            && $request->user()->hasPermission('accounting.year_end.'.$action), 403);
        match ($action) {
            'review' => $service->review($closingRun, $request->validated('notes', '')),
            'approve' => $service->approve($closingRun, $request->validated('notes', '')),
            'execute' => $service->execute($closingRun),
        };

        return back()->with('success', 'تم تنفيذ إجراء إقفال السنة.');
    }

    public function startReopen(
        AccountingClosingActionRequest $request,
        FiscalYear $fiscalYear,
        AccountingReopenService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('accounting.year_end.reopen'), 403);
        $service->startFiscalYear($fiscalYear, $request->validated('reason'));

        return back()->with('success', 'تم إرسال طلب إعادة فتح السنة للموافقة المستقلة.');
    }

    public function approveReopen(
        AccountingClosingActionRequest $request,
        AccountingClosingRun $closingRun,
        AccountingReopenService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('accounting.year_end.reopen'), 403);
        $service->approveFiscalYear($closingRun, $request->validated('notes', ''));

        return back()->with('success', 'تمت إعادة الفتح بعكس القيود مع الحفاظ على التاريخ.');
    }
}
