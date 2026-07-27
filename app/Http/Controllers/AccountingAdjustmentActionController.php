<?php

namespace App\Http\Controllers;

use App\Models\AccountingAdjustment;
use App\Services\AccountingAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountingAdjustmentActionController extends Controller
{
    public function __invoke(Request $request, AccountingAdjustment $accountingAdjustment, string $action, AccountingAdjustmentService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.adjustments.'.$action), 403);
        $service->action($accountingAdjustment, $action);

        return back()->with('success', 'تم تحديث قيد التسوية.');
    }
}
