<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\EmployeeAdvanceRequest;
use App\Http\Requests\EmployeeAdvanceSettlementRequest;
use App\Http\Requests\EmployeeCommissionRuleRequest;
use App\Http\Requests\EmployeeCommissionSettlementRequest;
use App\Http\Requests\EmployeeExpenseClaimRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeCommissionAccrual;
use App\Models\EmployeeCommissionRule;
use App\Models\EmployeeCommissionSettlement;
use App\Models\EmployeeExpenseClaim;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\Tax;
use App\Services\EmployeeFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeFinanceController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $companyId = $tenant->companyId();
        $branchIds = $tenant->user()->isCompanyAdministrator()
            ? null : $tenant->user()->accessibleBranches()->pluck('branches.id');
        $branch = fn ($query) => $query->when(
            $branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds)
        );

        return view('employee-finance.index', [
            'employees' => Employee::query()->where('company_id', $companyId)->where('status', 'active')
                ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))->get(),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds))->get(),
            'currencies' => Currency::query()->where('is_active', true)->get(),
            'taxes' => Tax::query()->where('company_id', $companyId)->where('is_active', true)->get(),
            'accounts' => Account::query()->where('company_id', $companyId)
                ->where('is_active', true)->where('is_posting', true)->get(),
            'rules' => EmployeeCommissionRule::query()->where('company_id', $companyId)
                ->when($branchIds !== null, fn ($q) => $q->where(
                    fn ($scope) => $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds)
                ))->latest()->get(),
            'accruals' => $branch(EmployeeCommissionAccrual::query()->where('company_id', $companyId))
                ->with('employee')->latest()->limit(100)->get(),
            'settlements' => $branch(EmployeeCommissionSettlement::query()->where('company_id', $companyId))
                ->with('employee')->latest()->limit(100)->get(),
            'claims' => $branch(EmployeeExpenseClaim::query()->where('company_id', $companyId))
                ->with('employee')->latest()->limit(100)->get(),
            'advances' => $branch(EmployeeAdvance::query()->where('company_id', $companyId))
                ->with('employee')->latest()->limit(100)->get(),
        ]);
    }

    public function storeRule(
        EmployeeCommissionRuleRequest $request,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->saveRule(new EmployeeCommissionRule, $request->validated());

        return back()->with('success', 'Commission rule saved.');
    }

    public function calculate(Request $request, SalesInvoice $salesInvoice, EmployeeFinanceService $service): RedirectResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer']]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $count = count($service->calculateInvoice($salesInvoice, $employee));

        return back()->with('success', "{$count} commission accruals calculated.");
    }

    public function calculateCreditAdjustment(
        Request $request,
        SalesCreditNote $salesCreditNote,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $data = $request->validate(['employee_id' => ['required', 'integer']]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $count = count($service->calculateCreditNoteAdjustment($salesCreditNote, $employee));

        return back()->with('success', "{$count} commission adjustments calculated.");
    }

    public function accrualAction(
        EmployeeCommissionAccrual $commissionAccrual,
        string $action,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->accrualAction($commissionAccrual, $action);

        return back()->with('success', 'Commission accrual updated.');
    }

    public function storeSettlement(
        EmployeeCommissionSettlementRequest $request,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $data = $request->validated();
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $service->createSettlement($employee, $data['lines'], $data);

        return back()->with('success', 'Commission settlement created.');
    }

    public function settlementAction(
        EmployeeCommissionSettlement $commissionSettlement,
        string $action,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->settlementAction($commissionSettlement, $action);

        return back()->with('success', 'Commission settlement updated.');
    }

    public function storeExpense(
        EmployeeExpenseClaimRequest $request,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $service->createExpenseClaim($employee, $request->validated());

        return back()->with('success', 'Expense claim created.');
    }

    public function expenseAction(
        Request $request,
        EmployeeExpenseClaim $expenseClaim,
        string $action,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->expenseAction($expenseClaim, $action, $request->string('reason')->toString());

        return back()->with('success', 'Expense claim updated.');
    }

    public function storeAdvance(
        EmployeeAdvanceRequest $request,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $service->createAdvance($employee, $request->validated());

        return back()->with('success', 'Employee advance created.');
    }

    public function advanceAction(
        EmployeeAdvance $employeeAdvance,
        string $action,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->advanceAction($employeeAdvance, $action);

        return back()->with('success', 'Employee advance updated.');
    }

    public function settleAdvance(
        EmployeeAdvanceSettlementRequest $request,
        EmployeeAdvance $employeeAdvance,
        EmployeeFinanceService $service
    ): RedirectResponse {
        $service->settleAdvance($employeeAdvance, $request->validated());

        return back()->with('success', 'Employee advance settled.');
    }
}
