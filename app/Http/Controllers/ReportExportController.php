<?php

namespace App\Http\Controllers;

use App\Analytics\ReportFilterData;
use App\Analytics\ReportRegistry;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AnalyticsReportRequest;
use App\Services\AnalyticsReportService;
use App\Services\ReportExportService;
use App\Services\UnifiedAuditService;
use Illuminate\Support\Facades\DB;

class ReportExportController extends Controller
{
    public function __invoke(
        AnalyticsReportRequest $request,
        string $report,
        TenantContext $tenant,
        ReportRegistry $registry,
        AnalyticsReportService $service,
        ReportExportService $export,
        UnifiedAuditService $audit
    ) {
        $definition = $registry->get($report);
        $user = $request->user();
        abort_unless(
            $user->hasRole('system_admin')
                || ($user->hasPermission($definition['permission']) && $user->hasPermission('reports.export')),
            403
        );
        if ($definition['sensitive']) {
            abort_unless($user->hasRole('system_admin') || $user->hasPermission('reports.export_sensitive'), 403);
        }
        $filters = ReportFilterData::from($request->validated(), $tenant);
        $result = $service->run($report, $filters, $definition['export_row_limit']);
        abort_if($result->rows->count() > $definition['export_row_limit'], 422, 'Export row limit exceeded.');
        $format = $request->input('format', 'csv');
        $metadata = $this->metadata($definition, $filters, $tenant, $result->meta);
        $audit->record('report.exported', 'analytics', 'export', null, [
            'new_values' => [
                'report' => $report, 'format' => $format,
                'date_from' => $filters->dateFrom, 'date_to' => $filters->dateTo,
                'branch_ids' => $filters->branchIds, 'row_count' => $result->rows->count(),
            ],
        ]);

        if ($format === 'csv') {
            return $export->csv("{$report}.csv", $definition['columns'], $result->rows, $metadata, $filters);
        }
        if ($format === 'xlsx') {
            return $export->xlsx("{$report}.xlsx", $definition['columns'], $result->rows, $metadata, $filters);
        }

        return response()->view('analytics.print', [
            'definition' => $definition,
            'result' => $result,
            'rows' => $result->rows,
            'filters' => $filters,
            'metadata' => $metadata,
            'pdfReady' => $format === 'pdf',
        ])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Report-Output', $format === 'pdf' ? 'print-to-pdf' : 'print');
    }

    private function metadata(
        array $definition,
        ReportFilterData $filters,
        TenantContext $tenant,
        array $resultMeta
    ): array {
        return [
            'report_name' => $definition['name'],
            'company_name' => $tenant->company()->name,
            'branch_names' => $tenant->accessibleBranches()->whereIn('id', $filters->branchIds)->pluck('name')->join(', '),
            'currency_code' => DB::table('currencies')
                ->where('id', $resultMeta['currency_id'] ?? ($filters->currencyId ?: $tenant->company()->currency_id))
                ->value('code'),
            'generated_by' => $tenant->user()->name,
        ];
    }
}
