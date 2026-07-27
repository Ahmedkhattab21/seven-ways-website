<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeFinanceReportController extends Controller
{
    public function __invoke(Request $request, string $report, TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('employee_finance.reports'), 403);
        $companyId = $tenant->companyId();
        $branchIds = $tenant->user()->isCompanyAdministrator()
            ? null : $tenant->accessibleBranches()->pluck('id');
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $rows = match ($report) {
            'commission-accruals', 'commission-balance' => $this->scope(
                DB::table('employee_commission_accruals as a'), $filters, 'a', 'accrual_date', $branchIds
            )
                ->join('employees as e', 'e.id', '=', 'a.employee_id')
                ->join('currencies as c', 'c.id', '=', 'a.currency_id')
                ->where('a.company_id', $companyId)
                ->selectRaw('e.name employee, c.code currency, SUM(a.commission_amount) accrued, SUM(a.settled_amount) settled, SUM(a.commission_amount-a.settled_amount) outstanding')
                ->groupBy('e.id', 'e.name', 'c.code')->get(),
            'commission-settlements' => $this->scope(
                DB::table('employee_commission_settlements as s'), $filters, 's', 'settlement_date', $branchIds
            )
                ->join('employees as e', 'e.id', '=', 's.employee_id')
                ->where('s.company_id', $companyId)
                ->select('s.settlement_number', 'e.name as employee', 's.settlement_date', 's.total_amount', 's.status')->get(),
            'expense-claims', 'expense-analysis' => $this->scope(
                DB::table('employee_expense_claims as x'), $filters, 'x', 'claim_date', $branchIds
            )
                ->join('employees as e', 'e.id', '=', 'x.employee_id')
                ->where('x.company_id', $companyId)
                ->select('x.claim_number', 'e.name as employee', 'x.claim_date', 'x.subtotal', 'x.tax_amount', 'x.total_amount', 'x.status')->get(),
            'outstanding-advances', 'custody-aging', 'employee-balances' => $this->scope(
                DB::table('employee_advances as a'), $filters, 'a', 'request_date', $branchIds
            )
                ->join('employees as e', 'e.id', '=', 'a.employee_id')
                ->where('a.company_id', $companyId)
                ->whereNotIn('a.status', ['closed', 'cancelled', 'reversed'])
                ->selectRaw('a.advance_number, e.name employee, a.advance_type, a.request_date, a.amount, a.settled_amount, (a.amount-a.settled_amount) outstanding, a.status')
                ->get(),
            default => abort(404),
        };

        return view('employee-finance.report', compact('report', 'rows', 'filters'));
    }

    private function scope(
        Builder $query,
        array $filters,
        string $alias,
        string $dateColumn,
        ?iterable $branchIds
    ): Builder {
        return $query
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn("{$alias}.branch_id", $branchIds))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $value) => $q->where("{$alias}.branch_id", $value))
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $value) => $q->where("{$alias}.employee_id", $value))
            ->when($filters['status'] ?? null, fn (Builder $q, $value) => $q->where("{$alias}.status", $value))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $value) => $q->whereDate("{$alias}.{$dateColumn}", '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $value) => $q->whereDate("{$alias}.{$dateColumn}", '<=', $value));
    }
}
