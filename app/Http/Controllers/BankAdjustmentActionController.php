<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAdjustmentActionRequest;
use App\Models\BankAdjustment;
use App\Services\BankAdjustmentService;
use Illuminate\Http\RedirectResponse;

class BankAdjustmentActionController extends Controller
{
    public function __invoke(
        BankAdjustmentActionRequest $request,
        BankAdjustment $bankAdjustment,
        string $action,
        BankAdjustmentService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_adjustments.'.$action), 403);
        if (in_array($action, ['approve', 'post'], true)) {
            $this->authorize($action, $bankAdjustment);
        }
        $service->action($bankAdjustment, $action, $request->validated());

        return back()->with('success', 'تم تحديث دورة تسوية البنك.');
    }
}
