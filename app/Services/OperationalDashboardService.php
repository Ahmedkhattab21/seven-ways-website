<?php

namespace App\Services;

use App\Analytics\ReportFilterData;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class OperationalDashboardService
{
    private const FINAL_INVOICE_STATUSES = ['issued', 'partially_paid', 'paid', 'overdue', 'credited'];

    private const FINAL_CREDIT_STATUSES = ['issued', 'partially_applied', 'applied', 'refunded'];

    private const COLLECTED_PAYMENT_STATUSES = ['approved', 'partially_allocated', 'allocated'];

    public function summary(ReportFilterData $filters): array
    {
        $currencyId = $filters->currencyId
            ?? DB::table('companies')->where('id', $filters->companyId)->value('currency_id');
        $invoices = DB::table('sales_invoices')
            ->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $currencyId)
            ->whereIn('status', self::FINAL_INVOICE_STATUSES)
            ->whereBetween('invoice_date', [$filters->dateFrom, $filters->dateTo]);
        $credits = DB::table('sales_credit_notes')
            ->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $currencyId)
            ->whereIn('status', self::FINAL_CREDIT_STATUSES)
            ->whereBetween('credit_note_date', [$filters->dateFrom, $filters->dateTo]);
        $invoiceTotal = (string) (clone $invoices)->sum('total');
        $creditTotal = (string) (clone $credits)->sum('total');

        $inventory = DB::table('stock_balances')
            ->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds);
        $lowStock = DB::table('stock_balances as sb')
            ->join('branch_products as bp', function ($join) {
                $join->on('bp.company_id', '=', 'sb.company_id')
                    ->on('bp.branch_id', '=', 'sb.branch_id')
                    ->on('bp.product_id', '=', 'sb.product_id');
            })
            ->where('sb.company_id', $filters->companyId)
            ->whereIn('sb.branch_id', $filters->branchIds)
            ->where('bp.is_available', true)
            ->whereRaw('COALESCE(bp.minimum_stock, 0) > 0')
            ->whereRaw('sb.available_quantity < bp.minimum_stock');

        return [
            'invoice_sales' => $invoiceTotal,
            'credit_notes' => $creditTotal,
            'net_sales' => bcsub($invoiceTotal, $creditTotal, 4),
            'invoice_count' => (clone $invoices)->count(),
            'collections' => (string) DB::table('customer_payments')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->where('currency_id', $currencyId)
                ->whereIn('status', self::COLLECTED_PAYMENT_STATUSES)
                ->whereBetween('payment_date', [$filters->dateFrom, $filters->dateTo])->sum('amount'),
            'receivables' => (string) DB::table('sales_invoices')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->where('currency_id', $currencyId)
                ->whereIn('status', self::FINAL_INVOICE_STATUSES)->sum('balance_due'),
            'inventory_value' => (string) (clone $inventory)
                ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) total')->value('total'),
            'low_stock_count' => (clone $lowStock)->distinct()->count('sb.product_id'),
            'negative_stock_count' => (clone $inventory)->where('available_quantity', '<', 0)->count(),
            'open_purchase_orders' => DB::table('purchase_orders')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->whereNotIn('status', ['closed', 'cancelled', 'fully_received'])->count(),
            'pending_goods_receipts' => DB::table('goods_receipts')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->whereNotIn('status', ['posted', 'cancelled'])->count(),
            'pending_inventory_counts' => DB::table('inventory_counts')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->whereNotIn('status', ['posted', 'cancelled'])->count(),
            'pending_approvals' => $this->pendingApprovals($filters),
        ];
    }

    public function byBranch(ReportFilterData $filters): array
    {
        $branches = Branch::query()->where('company_id', $filters->companyId)
            ->whereIn('id', $filters->branchIds)->orderBy('name')->get();

        return $branches->map(function (Branch $branch) use ($filters) {
            $branchFilters = new ReportFilterData(
                $filters->companyId,
                [$branch->id],
                $filters->dateFrom,
                $filters->dateTo,
                $filters->currencyId
            );

            return ['branch' => $branch, 'metrics' => $this->summary($branchFilters)];
        })->all();
    }

    private function pendingApprovals(ReportFilterData $filters): int
    {
        return DB::table('sales_invoices')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)->where('status', 'pending_approval')->count()
            + DB::table('purchase_orders')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->where('status', 'pending_approval')->count()
            + DB::table('journal_entries')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->where('status', 'pending_approval')->count();
    }
}
