<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingModuleLockRequest;
use App\Models\AccountingPeriod;
use App\Services\AccountingModuleLockService;
use Illuminate\Http\RedirectResponse;

class AccountingModuleLockController extends Controller
{
    public function __invoke(AccountingModuleLockRequest $request, AccountingPeriod $accountingPeriod, AccountingModuleLockService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.periods.manage_module_locks'), 403);
        $service->update($accountingPeriod, $request->validated('modules'), $request->validated('reason'));

        return back()->with('success', 'تم تحديث أقفال الموديولات.');
    }
}
