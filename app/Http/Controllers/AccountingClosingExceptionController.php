<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingClosingExceptionActionRequest;
use App\Models\AccountingClosingException;
use App\Services\AccountingClosingExceptionService;
use Illuminate\Http\RedirectResponse;

class AccountingClosingExceptionController extends Controller
{
    public function __invoke(
        AccountingClosingExceptionActionRequest $request,
        AccountingClosingException $closingException,
        string $action,
        AccountingClosingExceptionService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('accounting.closing_exceptions.'.$action), 403);
        $service->action($closingException, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث استثناء الإقفال.');
    }
}
