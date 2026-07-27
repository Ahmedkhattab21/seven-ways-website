<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankMatchingRuleRequest;
use App\Models\BankMatchingRule;
use App\Services\BankMatchingRuleService;
use App\Services\BankReconciliationScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankMatchingRuleController extends Controller
{
    public function index(TenantContext $tenant, BankReconciliationScopeService $scope): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.matching_rules.view'), 403);

        return view('treasury.matching-rules', [
            'rules' => BankMatchingRule::query()->where('company_id', $tenant->companyId())
                ->where(fn ($query) => $query->whereNull('bank_account_id')->orWhereIn('bank_account_id', $scope->accountIds()))
                ->with('bankAccount')->orderBy('priority')->get(),
            'accounts' => $scope->accountQuery()->get(),
        ]);
    }

    public function store(BankMatchingRuleRequest $request, BankMatchingRuleService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.matching_rules.create'), 403);
        $service->save($request->validated());

        return back()->with('success', 'تم إنشاء قاعدة المطابقة.');
    }

    public function update(
        BankMatchingRuleRequest $request,
        BankMatchingRule $bankMatchingRule,
        BankMatchingRuleService $service
    ): RedirectResponse {
        $this->authorize('update', $bankMatchingRule);
        $service->save($request->validated(), $bankMatchingRule);

        return back()->with('success', 'تم تحديث قاعدة المطابقة.');
    }

    public function disable(BankMatchingRule $bankMatchingRule, BankMatchingRuleService $service): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('treasury.matching_rules.disable'), 403);
        $service->disable($bankMatchingRule);

        return back()->with('success', 'تم تعطيل قاعدة المطابقة.');
    }
}
