<?php

namespace App\Services;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class AccountingDashboardService
{
    public function __construct(
        private AnalyticsReportService $reports,
        private UnpostedAccountingSourcesService $unposted,
        private TenantContext $tenant
    ) {
    }

    public function build(ReportFilterData $filters): array
    {
        $financial = $this->reports->run('financial', $filters, 8);
        $receivables = $this->reports->run('receivables', $filters, 8);
        $payables = $this->reports->run('payables', $filters, 8);
        $treasury = $this->reports->run('treasury', $filters, 8);
        $journalScope = DB::table('journal_entries')->where('company_id', $filters->companyId)
            ->where(function ($query) use ($filters) {
                $query->whereIn('branch_id', $filters->branchIds);
                if ($filters->includeCompanyWide) {
                    $query->orWhereNull('branch_id');
                }
            })->whereBetween('entry_date', [$filters->dateFrom, $filters->dateTo]);
        $unposted = $this->unposted->report(['branch_ids' => $filters->branchIds]);

        return [
            'period' => [$filters->dateFrom, $filters->dateTo],
            'posted' => $financial->summary,
            'receivables' => $receivables->summary,
            'payables' => $payables->summary,
            'treasury' => $treasury->summary,
            'journals' => [
                'draft' => (clone $journalScope)->where('status', 'draft')->count(),
                'pending' => (clone $journalScope)->whereIn('status', ['submitted', 'pending_approval', 'approved'])->count(),
                'posted' => (clone $journalScope)->where('status', 'posted')->count(),
            ],
            'unposted_count' => $this->unposted->count(['branch_ids' => $filters->branchIds]),
            'unposted' => $unposted->take(8)->values(),
            'current_period' => DB::table('accounting_periods')->where('company_id', $filters->companyId)
                ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->first(),
            'latest_journals' => (clone $journalScope)->latest('entry_date')->latest('id')->limit(8)->get(),
            'quickActions' => $this->quickActions(),
        ];
    }

    private function quickActions(): array
    {
        $user = $this->tenant->user();

        return collect([
            ['route' => 'accounting.journals.create', 'permission' => 'accounting.journals.create', 'label' => 'قيد يومية جديد'],
            ['route' => 'accounting.posting.index', 'permission' => 'accounting.posting.execute', 'label' => 'مصادر غير مرحلة'],
            ['route' => 'accounting.reports.trial-balance', 'permission' => 'accounting.trial_balance.view', 'label' => 'ميزان المراجعة'],
            ['route' => 'accounting.reports.general-ledger', 'permission' => 'accounting.general_ledger.view', 'label' => 'دفتر الأستاذ'],
            ['route' => 'accounting.reports.income-statement', 'permission' => 'accounting.income_statement.view', 'label' => 'قائمة الدخل'],
            ['route' => 'accounting.reports.balance-sheet', 'permission' => 'accounting.balance_sheet.view', 'label' => 'الميزانية'],
        ])->filter(fn (array $action) => Route::has($action['route']) && $user?->hasPermission($action['permission']))
            ->map(fn (array $action) => $action + ['url' => route($action['route'])])->values()->all();
    }
}
