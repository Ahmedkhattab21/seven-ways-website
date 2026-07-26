<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\TrialBalanceRequest;
use App\Services\AuditService;
use App\Services\FinancialReportExportService;
use App\Services\FinancialReportViewDataService;
use App\Services\TrialBalanceService;

class TrialBalanceController extends Controller
{
    public function __invoke(
        TrialBalanceRequest $request,
        TrialBalanceService $service,
        FinancialReportViewDataService $viewData,
        FinancialReportExportService $export,
        AuditService $audit,
        TenantContext $tenant
    ) {
        abort_unless($request->user()->hasPermission('accounting.trial_balance.view'), 403);
        $report = $service->report($request->validated());
        if ($request->input('export') === 'csv') {
            abort_unless($request->user()->hasPermission('accounting.trial_balance.export'), 403);
            $audit->record('financial_report.exported', $tenant->company(), [
                'report' => 'trial_balance', 'filters' => $request->safe()->except(['export']),
            ]);

            return $export->csv('trial-balance.csv', ['الكود', 'الحساب', 'افتتاحي مدين', 'افتتاحي دائن', 'حركة مدين', 'حركة دائن', 'ختامي مدين', 'ختامي دائن'], $report['rows']->map(fn ($r) => [
                $r->account_code, $r->name_ar, $r->opening_debit, $r->opening_credit,
                $r->period_debit, $r->period_credit, $r->closing_debit, $r->closing_credit,
            ]));
        }

        return view('accounting.reports.trial-balance', $report + $viewData->filters());
    }
}
