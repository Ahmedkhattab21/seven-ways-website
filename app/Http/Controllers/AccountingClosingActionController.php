<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingClosingActionRequest;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Services\AccountingPeriodClosingService;
use App\Services\AccountingReopenService;
use Illuminate\Http\RedirectResponse;

class AccountingClosingActionController extends Controller
{
    public function start(AccountingClosingActionRequest $request, AccountingPeriod $accountingPeriod, AccountingPeriodClosingService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.closing.start'), 403);
        $service->start($accountingPeriod, $request->validated('closing_type'), $request->validated('reason'));

        return back()->with('success', 'تم بدء فحص الإقفال.');
    }

    public function run(AccountingClosingActionRequest $request, AccountingClosingRun $closingRun, string $action, AccountingPeriodClosingService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.closing.'.$action), 403);
        $action === 'review'
            ? $service->review($closingRun, $request->validated('notes') ?? $request->validated('reason'))
            : $service->approve($closingRun, $request->validated('notes') ?? $request->validated('reason'));

        return back()->with('success', 'تم تحديث دورة الإقفال.');
    }

    public function lock(AccountingClosingActionRequest $request, AccountingPeriod $accountingPeriod, AccountingPeriodClosingService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.periods.lock'), 403);
        $service->lock($accountingPeriod, $request->validated('reason'));

        return back()->with('success', 'تم قفل الفترة.');
    }

    public function reopen(AccountingClosingActionRequest $request, AccountingPeriod $accountingPeriod, AccountingReopenService $service): RedirectResponse
    {
        $exceptional = $accountingPeriod->status === 'locked';
        $permission = $exceptional ? 'accounting.periods.reopen_locked' : 'accounting.periods.reopen';
        abort_unless($request->user()->hasPermission($permission), 403);
        $service->period($accountingPeriod, $request->validated('reason'), $exceptional);

        return back()->with('success', 'تمت إعادة فتح الفترة مع حفظ التاريخ.');
    }
}
