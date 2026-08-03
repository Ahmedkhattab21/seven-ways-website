<?php

namespace App\Services;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Models\CashBox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class BranchOperationalDashboardService
{
    public function __construct(
        private TenantContext $tenant,
        private OperationalDashboardService $operations,
        private TreasuryBalanceService $treasury
    ) {
    }

    public function build(): array
    {
        $branch = $this->tenant->branch();
        abort_unless($branch, 403);
        $today = CarbonImmutable::today();
        $filters = new ReportFilterData(
            (int) $this->tenant->companyId(),
            [(int) $branch->id],
            $today->toDateString(),
            $today->toDateString(),
            (int) $this->tenant->company()?->currency_id
        );
        $trendFilters = new ReportFilterData(
            $filters->companyId,
            $filters->branchIds,
            $today->subDays(6)->toDateString(),
            $today->toDateString(),
            $filters->currencyId
        );
        $metrics = $this->operations->summary($filters);
        $cash = $this->cashSummary((int) $branch->id);

        return [
            'branch' => $branch,
            'period' => [$filters->dateFrom, $filters->dateTo],
            'metrics' => $metrics + $cash,
            'trend' => $this->trend($trendFilters),
            'activities' => $this->activities((int) $branch->id),
            'alerts' => $this->alerts($metrics, $cash),
            'quickActions' => $this->quickActions(),
        ];
    }

    private function trend(ReportFilterData $filters): array
    {
        $invoiceRows = DB::table('sales_invoices')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $filters->currencyId)
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])
            ->whereBetween('invoice_date', [$filters->dateFrom, $filters->dateTo])
            ->selectRaw('invoice_date as day, SUM(total) total')->groupBy('invoice_date')->pluck('total', 'day');
        $creditRows = DB::table('sales_credit_notes')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $filters->currencyId)
            ->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded'])
            ->whereBetween('credit_note_date', [$filters->dateFrom, $filters->dateTo])
            ->selectRaw('credit_note_date as day, SUM(total) total')->groupBy('credit_note_date')->pluck('total', 'day');

        $rows = [];
        for ($date = CarbonImmutable::parse($filters->dateFrom); $date->lte(CarbonImmutable::parse($filters->dateTo)); $date = $date->addDay()) {
            $day = $date->toDateString();
            $rows[] = ['date' => $day, 'net' => bcsub((string) ($invoiceRows[$day] ?? 0), (string) ($creditRows[$day] ?? 0), 4)];
        }

        return $rows;
    }

    private function activities(int $branchId): array
    {
        $scope = fn ($query) => $query->where('company_id', $this->tenant->companyId())->where('branch_id', $branchId);
        $rows = collect();
        foreach ([
            ['sales_invoices', 'invoice_number', 'فاتورة مبيعات'],
            ['customer_payments', 'payment_number', 'تحصيل عميل'],
            ['purchase_orders', 'purchase_order_number', 'أمر شراء'],
        ] as [$table, $number, $label]) {
            $rows = $rows->concat($scope(DB::table($table))->latest('created_at')->limit(5)
                ->get([$number, 'status', 'created_at'])->map(fn ($row) => [
                    'label' => $label,
                    'number' => $row->{$number},
                    'status' => $row->status,
                    'at' => $row->created_at,
                ]));
        }

        return $rows->sortByDesc('at')->take(8)->values()->all();
    }

    private function cashSummary(int $branchId): array
    {
        $user = $this->tenant->user();
        if (! $user?->hasPermission('treasury.cash_boxes.view')) {
            return ['cash_session' => null, 'cash_book_balance' => null];
        }
        $box = CashBox::query()->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $branchId)->where('status', 'active')->orderByDesc('is_primary')->first();
        if (! $box) {
            return ['cash_session' => null, 'cash_book_balance' => null];
        }
        $session = DB::table('cash_box_sessions')->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $branchId)->where('cash_box_id', $box->id)
            ->whereNotIn('status', ['closed', 'cancelled'])->latest('id')->first();

        return [
            'cash_session' => $session,
            'cash_book_balance' => $this->treasury->cashBox($box)['book_balance'],
        ];
    }

    private function alerts(array $metrics, array $cash): array
    {
        return collect([
            $metrics['negative_stock_count'] > 0 ? ['level' => 'danger', 'text' => 'يوجد مخزون سالب يحتاج مراجعة.'] : null,
            $metrics['low_stock_count'] > 0 ? ['level' => 'warning', 'text' => 'توجد منتجات أقل من الحد الأدنى.'] : null,
            $metrics['pending_approvals'] > 0 ? ['level' => 'warning', 'text' => 'توجد مستندات بانتظار الاعتماد.'] : null,
            $cash['cash_session'] === null && $this->tenant->user()?->hasPermission('treasury.cash_boxes.view')
                ? ['level' => 'info', 'text' => 'لا توجد جلسة خزينة نشطة للفرع.'] : null,
        ])->filter()->values()->all();
    }

    private function quickActions(): array
    {
        $user = $this->tenant->user();

        return collect([
            ['route' => 'customers.create', 'permission' => 'customers.create', 'label' => 'إضافة عميل'],
            ['route' => 'sales-invoices.create', 'permission' => 'sales_invoices.direct_sale', 'label' => 'فاتورة مبيعات جديدة'],
            ['route' => 'customer-payments.create', 'permission' => 'customer_payments.record', 'label' => 'تسجيل تحصيل'],
            ['route' => 'purchase-orders.create', 'permission' => 'purchase_orders.create', 'label' => 'أمر شراء جديد'],
            ['route' => 'goods-receipts.create', 'permission' => 'goods_receipts.create', 'label' => 'استلام مشتريات'],
        ])->filter(fn (array $action) => Route::has($action['route']) && $user?->hasPermission($action['permission']))
            ->map(fn (array $action) => $action + ['url' => route($action['route'])])->values()->all();
    }
}
