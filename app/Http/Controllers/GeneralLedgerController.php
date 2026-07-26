<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\GeneralLedgerReportRequest;
use App\Services\AuditService;
use App\Services\FinancialReportExportService;
use App\Services\FinancialReportViewDataService;
use App\Services\GeneralLedgerService;

class GeneralLedgerController extends Controller
{
    public function __invoke(
        GeneralLedgerReportRequest $request,
        GeneralLedgerService $service,
        FinancialReportViewDataService $viewData,
        FinancialReportExportService $export,
        AuditService $audit,
        TenantContext $tenant
    ) {
        abort_unless($request->user()->hasPermission('accounting.general_ledger.view'), 403);
        $data = null;
        if ($request->filled('account_id')) {
            $data = $service->report($request->validated(), (int) $request->input('per_page', 50));
            if ($request->input('export') === 'csv') {
                abort_unless($request->user()->hasPermission('accounting.general_ledger.export'), 403);
                $rows = collect($data['lines']->items())->map(fn ($line) => [
                    $line->posting_date, $line->journal_number, $line->source_number, $line->description,
                    $line->base_debit_amount, $line->base_credit_amount, $line->running_balance,
                ]);
                $audit->record('financial_report.exported', $tenant->company(), [
                    'report' => 'general_ledger', 'filters' => $request->safe()->except(['export']),
                ]);

                return $export->csv('general-ledger.csv', ['التاريخ', 'القيد', 'المصدر', 'البيان', 'مدين', 'دائن', 'الرصيد'], $rows);
            }
        }

        return view('accounting.reports.general-ledger', ($data ?? []) + $viewData->filters());
    }
}
