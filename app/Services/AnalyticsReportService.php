<?php

namespace App\Services;

use App\Analytics\ReportFilterData;
use App\Analytics\ReportResult;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsReportService
{
    public function run(string $code, ReportFilterData $filters, int $limit = 200): ReportResult
    {
        $limit = min(max($limit, 1), 5000);

        return match ($code) {
            'financial' => $this->financial($filters, $limit),
            'sales' => $this->sales($filters, $limit),
            'receivables' => $this->receivables($filters, $limit),
            'purchases' => $this->purchases($filters, $limit),
            'payables' => $this->payables($filters, $limit),
            'inventory' => $this->inventory($filters, $limit),
            'treasury' => $this->treasury($filters, $limit),
            'employee-finance' => $this->employeeFinance($filters, $limit),
            'approvals' => $this->approvals($filters, $limit),
            'audit' => $this->audit($filters, $limit),
            default => abort(404),
        };
    }

    private function financial(ReportFilterData $filters, int $limit): ReportResult
    {
        $period = $this->postedLines($filters)
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->groupBy('at.classification')
            ->selectRaw('at.classification, SUM(jel.base_debit_amount) debit, SUM(jel.base_credit_amount) credit')
            ->get()->keyBy('classification');
        $asOf = $this->postedLines($filters, true)
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->groupBy('at.classification')
            ->selectRaw('at.classification, SUM(jel.base_debit_amount) debit, SUM(jel.base_credit_amount) credit')
            ->get()->keyBy('classification');

        $revenue = $this->creditBalance($period->get('revenue'));
        $expenses = $this->debitBalance($period->get('expense'));
        $assets = $this->debitBalance($asOf->get('asset'));
        $liabilities = $this->creditBalance($asOf->get('liability'));
        $equity = $this->creditBalance($asOf->get('equity'));
        $result = bcsub($revenue, $expenses, 4);
        $balanceDifference = bcsub($assets, bcadd(bcadd($liabilities, $equity, 4), $result, 4), 4);
        $totals = $this->postedLines($filters)
            ->selectRaw('COALESCE(SUM(jel.base_debit_amount),0) debit, COALESCE(SUM(jel.base_credit_amount),0) credit')
            ->first();

        $rows = DB::table('journal_entries as je')
            ->leftJoin('branches as b', 'b.id', '=', 'je.branch_id')
            ->where('je.company_id', $filters->companyId)->where('je.status', 'posted')
            ->whereBetween('je.posting_date', [$filters->dateFrom, $filters->dateTo])
            ->where(function (Builder $query) use ($filters) {
                $query->whereIn('je.branch_id', $filters->branchIds);
                if ($filters->includeCompanyWide) {
                    $query->orWhereNull('je.branch_id');
                }
            })
            ->selectRaw('je.posting_date, je.journal_number, je.source_number, je.description, je.base_total_debit debit, je.base_total_credit credit, COALESCE(b.name, ?) branch', ['عام'])
            ->orderByDesc('je.posting_date')->orderByDesc('je.id')->limit($limit)->get();

        return new ReportResult([
            'period_debit' => $this->decimal($totals?->debit),
            'period_credit' => $this->decimal($totals?->credit),
            'trial_balance_balanced' => bccomp($this->decimal($totals?->debit), $this->decimal($totals?->credit), 4) === 0,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'estimated_operating_result' => $result,
            'assets' => $assets,
            'liabilities_and_equity' => bcadd(bcadd($liabilities, $equity, 4), $result, 4),
            'balance_difference' => $balanceDifference,
            'balance_sheet_balanced' => bccomp($balanceDifference, '0', 4) === 0,
        ], $rows, [
            'data_source' => 'posted journal entries and lines',
            'currency_id' => DB::table('companies')->where('id', $filters->companyId)->value('currency_id'),
            'posted_only' => true,
        ]);
    }

    private function sales(ReportFilterData $filters, int $limit): ReportResult
    {
        $currencyId = $this->currencyId($filters);
        $base = $this->postedDocuments('sales_invoices as i', SalesInvoice::class, $filters, 'i.invoice_date')
            ->where('i.currency_id', $currencyId)
            ->when($filters->customerId, fn (Builder $q, int $id) => $q->where('i.customer_id', $id));
        $invoice = (clone $base)->selectRaw(
            'COUNT(DISTINCT i.id) invoice_count, COALESCE(SUM(i.subtotal),0) subtotal, '.
            'COALESCE(SUM(i.discount_amount),0) discounts, COALESCE(SUM(i.tax_amount),0) tax, COALESCE(SUM(i.total),0) total'
        )->first();
        $credits = $this->postedDocuments('sales_credit_notes as n', SalesCreditNote::class, $filters, 'n.credit_note_date')
            ->where('n.currency_id', $currencyId)
            ->when($filters->customerId, fn (Builder $q, int $id) => $q->where('n.customer_id', $id))
            ->selectRaw('COALESCE(SUM(n.subtotal),0) subtotal, COALESCE(SUM(n.tax_amount),0) tax, COALESCE(SUM(n.total),0) total')
            ->first();
        $trend = (clone $base)
            ->groupByRaw("DATE_FORMAT(i.invoice_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(i.invoice_date, '%Y-%m') period, SUM(i.subtotal-i.discount_amount) amount")
            ->pluck('amount', 'period');
        $creditTrend = $this->postedDocuments(
            'sales_credit_notes as n',
            SalesCreditNote::class,
            $filters,
            'n.credit_note_date'
        )->where('n.currency_id', $currencyId)
            ->when($filters->customerId, fn (Builder $q, int $id) => $q->where('n.customer_id', $id))
            ->groupByRaw("DATE_FORMAT(n.credit_note_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(n.credit_note_date, '%Y-%m') period, SUM(n.subtotal) amount")
            ->pluck('amount', 'period');
        foreach ($creditTrend as $period => $amount) {
            $trend[$period] = bcsub($this->decimal($trend->get($period)), $this->decimal($amount), 4);
        }
        $trend = $trend->sortKeys()->map(fn ($amount) => $this->decimal($amount))->all();
        $netBeforeTax = bcsub(
            bcsub($this->decimal($invoice->subtotal), $this->decimal($invoice->discounts), 4),
            $this->decimal($credits->subtotal),
            4
        );
        $average = (int) $invoice->invoice_count === 0
            ? null : bcdiv(bcsub($this->decimal($invoice->total), $this->decimal($credits->total), 4), (string) $invoice->invoice_count, 4);

        $rows = (clone $base)
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->join('branches as b', 'b.id', '=', 'i.branch_id')
            ->join('currencies as cu', 'cu.id', '=', 'i.currency_id')
            ->selectRaw('i.invoice_date, i.invoice_number, c.name customer, b.name branch, (i.subtotal-i.discount_amount) net_sales, i.tax_amount tax, i.total, cu.code currency')
            ->orderByDesc('i.invoice_date')->orderByDesc('i.id')->limit($limit)->get();

        return new ReportResult([
            'gross_before_global_discount' => $this->decimal($invoice->subtotal),
            'discounts' => $this->decimal($invoice->discounts),
            'tax' => bcsub($this->decimal($invoice->tax), $this->decimal($credits->tax), 4),
            'credit_notes_before_tax' => $this->decimal($credits->subtotal),
            'net_sales_before_tax' => $netBeforeTax,
            'net_sales_after_tax' => bcsub($this->decimal($invoice->total), $this->decimal($credits->total), 4),
            'invoice_count' => (int) $invoice->invoice_count,
            'average_invoice_value' => $average,
        ], $rows, [
            'data_source' => 'posted accounting links and invoice snapshots',
            'currency_id' => $currencyId,
            'posted_only' => true,
            'sales_trend' => $trend,
        ]);
    }

    private function receivables(ReportFilterData $filters, int $limit): ReportResult
    {
        $currencyId = $this->currencyId($filters);
        $query = DB::table('sales_invoices as i')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->join('branches as b', 'b.id', '=', 'i.branch_id')
            ->join('currencies as cu', 'cu.id', '=', 'i.currency_id')
            ->where('i.company_id', $filters->companyId)->whereIn('i.branch_id', $filters->branchIds)
            ->where('i.currency_id', $currencyId)->where('i.balance_due', '>', 0)
            ->whereIn('i.status', ['issued', 'partially_paid', 'overdue'])
            ->when($filters->customerId, fn (Builder $q, int $id) => $q->where('i.customer_id', $id));
        $invoices = (clone $query)->select(
            'i.id', 'i.due_date', 'i.invoice_number', 'c.name as customer', 'b.name as branch',
            'i.balance_due as balance', 'cu.code as currency'
        )->orderBy('i.due_date')->limit($limit)->get()->map(function ($row) {
            $row->bucket = $this->agingBucket($row->due_date);

            return $row;
        });
        $aging = $invoices->groupBy('bucket')->map(
            fn (Collection $rows) => $rows->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000')
        )->all();
        $balance = (clone $query)->sum('i.balance_due');
        $unallocated = DB::table('customer_payments')
            ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $currencyId)->whereNotIn('status', ['cancelled'])
            ->sum('unallocated_amount');

        return new ReportResult([
            'outstanding' => $this->decimal($balance),
            'overdue' => $invoices->whereIn('bucket', ['1-30', '31-60', '61-90', '91-120', 'over-120'])
                ->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000'),
            'unallocated_payments' => $this->decimal($unallocated),
            'invoice_count' => $invoices->count(),
            'aging' => $aging,
        ], $invoices, ['data_source' => 'issued invoice balances and payment allocations', 'currency_id' => $currencyId]);
    }

    private function purchases(ReportFilterData $filters, int $limit): ReportResult
    {
        $currencyId = $this->currencyId($filters);
        $base = $this->postedDocuments('supplier_invoices as i', SupplierInvoice::class, $filters, 'i.invoice_date')
            ->where('i.currency_id', $currencyId)
            ->when($filters->supplierId, fn (Builder $q, int $id) => $q->where('i.supplier_id', $id));
        $invoice = (clone $base)->selectRaw(
            'COUNT(DISTINCT i.id) invoice_count, COALESCE(SUM(i.subtotal-i.discount_amount),0) subtotal, '.
            'COALESCE(SUM(i.tax_amount),0) tax, COALESCE(SUM(i.total),0) total'
        )->first();
        $credits = $this->postedDocuments('supplier_credit_notes as n', SupplierCreditNote::class, $filters, 'n.credit_date')
            ->where('n.currency_id', $currencyId)
            ->when($filters->supplierId, fn (Builder $q, int $id) => $q->where('n.supplier_id', $id))
            ->selectRaw('COALESCE(SUM(n.subtotal),0) subtotal, COALESCE(SUM(n.tax_amount),0) tax, COALESCE(SUM(n.total),0) total')
            ->first();
        $rows = (clone $base)->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->join('branches as b', 'b.id', '=', 'i.branch_id')
            ->join('currencies as cu', 'cu.id', '=', 'i.currency_id')
            ->selectRaw('i.invoice_date, i.internal_invoice_number invoice_number, s.name supplier, b.name branch, (i.subtotal-i.discount_amount) subtotal, i.tax_amount tax, i.total, cu.code currency')
            ->orderByDesc('i.invoice_date')->orderByDesc('i.id')->limit($limit)->get();

        return new ReportResult([
            'purchases_before_tax' => bcsub($this->decimal($invoice->subtotal), $this->decimal($credits->subtotal), 4),
            'tax' => bcsub($this->decimal($invoice->tax), $this->decimal($credits->tax), 4),
            'credit_notes' => $this->decimal($credits->total),
            'net_total' => bcsub($this->decimal($invoice->total), $this->decimal($credits->total), 4),
            'invoice_count' => (int) $invoice->invoice_count,
            'open_po_commitments' => $this->decimal(DB::table('purchase_orders')
                ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
                ->where('currency_id', $currencyId)->whereIn('status', ['approved', 'sent', 'partially_received'])
                ->selectRaw('COALESCE(SUM(GREATEST(total-received_amount,0)),0) amount')->value('amount')),
        ], $rows, ['data_source' => 'posted supplier invoices and approved purchase orders', 'currency_id' => $currencyId]);
    }

    private function payables(ReportFilterData $filters, int $limit): ReportResult
    {
        $currencyId = $this->currencyId($filters);
        $query = DB::table('supplier_invoices as i')
            ->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->join('branches as b', 'b.id', '=', 'i.branch_id')
            ->join('currencies as cu', 'cu.id', '=', 'i.currency_id')
            ->where('i.company_id', $filters->companyId)->whereIn('i.branch_id', $filters->branchIds)
            ->where('i.currency_id', $currencyId)->where('i.balance_due', '>', 0)
            ->whereIn('i.status', ['posted', 'partially_paid', 'overdue'])
            ->when($filters->supplierId, fn (Builder $q, int $id) => $q->where('i.supplier_id', $id));
        $rows = (clone $query)->select(
            'i.due_date', 'i.internal_invoice_number as invoice_number', 's.name as supplier',
            'b.name as branch', 'i.balance_due as balance', 'cu.code as currency'
        )->orderBy('i.due_date')->limit($limit)->get()->map(function ($row) {
            $row->bucket = $this->agingBucket($row->due_date);

            return $row;
        });
        $aging = $rows->groupBy('bucket')->map(
            fn (Collection $items) => $items->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000')
        )->all();
        $unallocated = DB::table('supplier_payments')
            ->where('company_id', $filters->companyId)->whereIn('branch_id', $filters->branchIds)
            ->where('currency_id', $currencyId)->whereNotIn('status', ['cancelled'])
            ->sum('unallocated_amount');

        return new ReportResult([
            'outstanding' => $this->decimal((clone $query)->sum('i.balance_due')),
            'overdue' => $rows->whereIn('bucket', ['1-30', '31-60', '61-90', '91-120', 'over-120'])
                ->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000'),
            'unallocated_payments' => $this->decimal($unallocated),
            'invoice_count' => $rows->count(),
            'aging' => $aging,
        ], $rows, ['data_source' => 'posted supplier invoice balances and payment allocations', 'currency_id' => $currencyId]);
    }

    private function inventory(ReportFilterData $filters, int $limit): ReportResult
    {
        $query = DB::table('stock_balances as sb')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sb.warehouse_id')
            ->join('branches as b', 'b.id', '=', 'sb.branch_id')
            ->where('sb.company_id', $filters->companyId)->whereIn('sb.branch_id', $filters->branchIds)
            ->when($filters->productId, fn (Builder $q, int $id) => $q->where('sb.product_id', $id))
            ->when($filters->warehouseId, fn (Builder $q, int $id) => $q->where('sb.warehouse_id', $id));
        $rows = (clone $query)->selectRaw(
            'p.sku, p.name product, b.name branch, w.name warehouse, sb.quantity, sb.available_quantity available, '.
            'sb.average_cost unit_cost, (sb.quantity*sb.average_cost) valuation'
        )->orderBy('p.name')->limit($limit)->get();
        $totals = (clone $query)->selectRaw(
            'COALESCE(SUM(sb.quantity),0) quantity, COALESCE(SUM(sb.available_quantity),0) available, '.
            'COALESCE(SUM(sb.quantity*sb.average_cost),0) valuation, '.
            'SUM(CASE WHEN sb.available_quantity <= 0 THEN 1 ELSE 0 END) out_of_stock, '.
            'SUM(CASE WHEN sb.available_quantity > 0 AND sb.available_quantity <= p.minimum_stock THEN 1 ELSE 0 END) reorder'
        )->first();
        $slow = (clone $query)->where(function (Builder $q) use ($filters) {
            $q->whereNull('sb.last_movement_at')
                ->orWhere('sb.last_movement_at', '<', now()->subDays($filters->movementDays));
        })->count();

        return new ReportResult([
            'quantity_on_hand' => $this->decimal($totals->quantity),
            'available_quantity' => $this->decimal($totals->available),
            'stock_valuation' => $this->decimal($totals->valuation),
            'out_of_stock_items' => (int) $totals->out_of_stock,
            'reorder_items' => (int) $totals->reorder,
            'slow_moving_items' => $slow,
            'pending_transfers' => DB::table('stock_transfers')->where('company_id', $filters->companyId)
                ->where(fn (Builder $q) => $q->whereIn('from_branch_id', $filters->branchIds)
                    ->orWhereIn('to_branch_id', $filters->branchIds))
                ->whereNotIn('status', ['received', 'cancelled', 'reversed'])->count(),
        ], $rows, [
            'data_source' => 'official inventory balances and weighted/specific cost snapshots',
            'movement_days' => $filters->movementDays,
            'currency_id' => DB::table('companies')->where('id', $filters->companyId)->value('currency_id'),
        ]);
    }

    private function treasury(ReportFilterData $filters, int $limit): ReportResult
    {
        $query = $this->postedLines($filters, true)
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->leftJoin('branches as b', 'b.id', '=', DB::raw('COALESCE(jel.branch_id, je.branch_id)'))
            ->where(fn (Builder $q) => $q->where('a.is_cash_account', true)->orWhere('a.is_bank_account', true))
            ->groupBy('a.id', 'a.account_code', 'a.name_ar', 'a.is_cash_account', 'a.is_bank_account', 'b.id', 'b.name')
            ->selectRaw(
                'a.account_code, a.name_ar account, CASE WHEN a.is_bank_account=1 THEN ? ELSE ? END type, '.
                'COALESCE(b.name, ?) branch, SUM(jel.base_debit_amount-jel.base_credit_amount) balance',
                ['bank', 'cash', 'عام']
            );
        $rows = (clone $query)->orderBy('a.account_code')->limit($limit)->get();
        $cash = $rows->where('type', 'cash')->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000');
        $bank = $rows->where('type', 'bank')->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000');

        return new ReportResult([
            'cash_book_balance' => $cash,
            'bank_book_balance' => $bank,
            'pending_transfers' => DB::table('treasury_transfers')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)
                ->whereIn('status', ['pending_approval', 'approved', 'processing', 'failed'])->count(),
            'open_cash_sessions' => DB::table('cash_box_sessions')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'bounced_cheques' => DB::table('cheques')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->where('status', 'bounced')->count(),
            'pending_settlements' => DB::table('merchant_settlements')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->whereNotIn('status', ['posted', 'reversed'])->count(),
        ], $rows, [
            'data_source' => 'posted general-ledger lines for cash and bank accounts',
            'currency_id' => DB::table('companies')->where('id', $filters->companyId)->value('currency_id'),
            'posted_only' => true,
        ]);
    }

    private function employeeFinance(ReportFilterData $filters, int $limit): ReportResult
    {
        $employees = DB::table('employees as e')->join('branches as b', 'b.id', '=', 'e.branch_id')
            ->where('e.company_id', $filters->companyId)->whereIn('e.branch_id', $filters->branchIds)
            ->when($filters->employeeId, fn (Builder $q, int $id) => $q->where('e.id', $id))
            ->select('e.id', 'e.name as employee', 'b.name as branch')
            ->orderBy('e.name')->limit($limit)->get();
        $ids = $employees->pluck('id');
        $commissions = DB::table('employee_commission_accruals')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)->whereIn('employee_id', $ids)
            ->whereNotIn('status', ['reversed', 'cancelled'])
            ->groupBy('employee_id')->selectRaw('employee_id, SUM(commission_amount-settled_amount) amount')
            ->pluck('amount', 'employee_id');
        $expenses = DB::table('employee_expense_claims')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)->whereIn('employee_id', $ids)
            ->whereIn('status', ['posted', 'paid'])
            ->whereBetween('claim_date', [$filters->dateFrom, $filters->dateTo])
            ->groupBy('employee_id')->selectRaw('employee_id, SUM(total_amount) amount')
            ->pluck('amount', 'employee_id');
        $advances = DB::table('employee_advances')->where('company_id', $filters->companyId)
            ->whereIn('branch_id', $filters->branchIds)->whereIn('employee_id', $ids)
            ->whereNotIn('status', ['closed', 'cancelled', 'reversed'])
            ->groupBy('employee_id')->selectRaw('employee_id, SUM(amount-settled_amount) amount')
            ->pluck('amount', 'employee_id');
        $rows = $employees->map(function ($employee) use ($commissions, $expenses, $advances) {
            $employee->commission_outstanding = $this->decimal($commissions->get($employee->id));
            $employee->expenses_posted = $this->decimal($expenses->get($employee->id));
            $employee->advances_outstanding = $this->decimal($advances->get($employee->id));
            unset($employee->id);

            return $employee;
        });

        return new ReportResult([
            'commission_outstanding' => $rows->reduce(fn ($sum, $row) => bcadd($sum, $row->commission_outstanding, 4), '0.0000'),
            'expenses_posted' => $rows->reduce(fn ($sum, $row) => bcadd($sum, $row->expenses_posted, 4), '0.0000'),
            'advances_outstanding' => $rows->reduce(fn ($sum, $row) => bcadd($sum, $row->advances_outstanding, 4), '0.0000'),
            'pending_expense_claims' => DB::table('employee_expense_claims')->where('company_id', $filters->companyId)
                ->whereIn('branch_id', $filters->branchIds)->whereIn('status', ['draft', 'pending_approval', 'approved'])->count(),
        ], $rows, ['data_source' => 'employee accruals, settlements, claims and advances; no stored KPI balances']);
    }

    private function approvals(ReportFilterData $filters, int $limit): ReportResult
    {
        $query = DB::table('approval_tasks as t')
            ->leftJoin('branches as b', 'b.id', '=', 't.branch_id')
            ->where('t.company_id', $filters->companyId)->whereIn('t.branch_id', $filters->branchIds)
            ->whereBetween(DB::raw('DATE(t.requested_at)'), [$filters->dateFrom, $filters->dateTo])
            ->when($filters->status, fn (Builder $q, string $status) => $q->where('t.status', $status));
        $rows = (clone $query)->selectRaw(
            't.requested_at, t.document_number, t.module, COALESCE(b.name, ?) branch, t.status, '.
            'TIMESTAMPDIFF(HOUR,t.requested_at,COALESCE(t.completed_at,NOW())) age_hours, t.amount_snapshot amount',
            ['عام']
        )->orderByDesc('t.requested_at')->limit($limit)->get();
        $completed = (clone $query)->whereNotNull('t.completed_at');
        $average = $completed->selectRaw('AVG(TIMESTAMPDIFF(MINUTE,t.requested_at,t.completed_at)) average')->value('average');

        return new ReportResult([
            'pending' => (clone $query)->where('t.status', 'pending')->count(),
            'overdue' => (clone $query)->where('t.status', 'pending')->where('t.due_at', '<', now())->count(),
            'approved' => (clone $query)->where('t.status', 'approved')->count(),
            'rejected' => (clone $query)->where('t.status', 'rejected')->count(),
            'delegated' => (clone $query)->whereNotNull('t.delegation_id')->count(),
            'average_turnaround_minutes' => $average === null ? null : round((float) $average, 2),
        ], $rows, ['data_source' => 'central approval tasks and immutable actions']);
    }

    private function audit(ReportFilterData $filters, int $limit): ReportResult
    {
        $query = DB::table('audit_events as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('branches as b', 'b.id', '=', 'a.branch_id')
            ->where('a.company_id', $filters->companyId)
            ->where(function (Builder $query) use ($filters) {
                $query->whereIn('a.branch_id', $filters->branchIds);
                if ($filters->includeCompanyWide) {
                    $query->orWhereNull('a.branch_id');
                }
            })
            ->whereBetween(DB::raw('DATE(a.occurred_at)'), [$filters->dateFrom, $filters->dateTo]);
        $rows = (clone $query)->selectRaw(
            'a.occurred_at, a.module, a.action, a.document_number, COALESCE(u.name, ?) actor, '.
            'COALESCE(b.name, ?) branch, a.correlation_id',
            ['نظام', 'عام']
        )->orderByDesc('a.occurred_at')->limit($limit)->get();

        return new ReportResult([
            'events' => (clone $query)->count(),
            'posting' => (clone $query)->where('a.action', 'like', '%post%')->count(),
            'reversal' => (clone $query)->where('a.action', 'like', '%revers%')->count(),
            'security' => (clone $query)->whereIn('a.module', ['auth', 'users', 'roles', 'permissions'])->count(),
        ], $rows, ['data_source' => 'immutable unified audit events; values remain masked and are not exported here']);
    }

    private function postedDocuments(
        string $table,
        string $modelClass,
        ReportFilterData $filters,
        string $dateColumn
    ): Builder {
        $alias = str_contains($table, ' as ') ? trim(strrchr($table, ' ')) : $table;

        return DB::table($table)
            ->join('accounting_posting_links as apl', function ($join) use ($alias, $modelClass) {
                $join->on('apl.source_id', '=', "{$alias}.id")
                    ->where('apl.source_type', '=', $modelClass)
                    ->where('apl.status', '=', 'posted');
            })
            ->where("{$alias}.company_id", $filters->companyId)
            ->whereIn("{$alias}.branch_id", $filters->branchIds)
            ->whereBetween($dateColumn, [$filters->dateFrom, $filters->dateTo]);
    }

    private function postedLines(ReportFilterData $filters, bool $asOf = false): Builder
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.company_id', $filters->companyId)->where('je.status', 'posted')
            ->when(
                $asOf,
                fn (Builder $q) => $q->whereDate('je.posting_date', '<=', $filters->dateTo),
                fn (Builder $q) => $q->whereBetween('je.posting_date', [$filters->dateFrom, $filters->dateTo])
            )
            ->where(function (Builder $query) use ($filters) {
                $query->whereIn(DB::raw('COALESCE(jel.branch_id,je.branch_id)'), $filters->branchIds);
                if ($filters->includeCompanyWide) {
                    $query->orWhere(function (Builder $global) {
                        $global->whereNull('jel.branch_id')->whereNull('je.branch_id');
                    });
                }
            });
    }

    private function currencyId(ReportFilterData $filters): int
    {
        return $filters->currencyId
            ?: (int) DB::table('companies')->where('id', $filters->companyId)->value('currency_id');
    }

    private function agingBucket(?string $dueDate): string
    {
        $age = $dueDate ? max(0, Carbon::parse($dueDate)->diffInDays(today(), false)) : 0;

        return match (true) {
            $age === 0 => 'current',
            $age <= 30 => '1-30',
            $age <= 60 => '31-60',
            $age <= 90 => '61-90',
            $age <= 120 => '91-120',
            default => 'over-120',
        };
    }

    private function debitBalance(?object $row): string
    {
        return bcsub($this->decimal($row?->debit), $this->decimal($row?->credit), 4);
    }

    private function creditBalance(?object $row): string
    {
        return bcsub($this->decimal($row?->credit), $this->decimal($row?->debit), 4);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }
}
