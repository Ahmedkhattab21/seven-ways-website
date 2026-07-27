<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliationActionRequest;
use App\Http\Requests\BankReconciliationReopenRequest;
use App\Models\BankReconciliationSession;
use App\Services\BankMatchingSuggestionService;
use App\Services\BankReconciliationReopenService;
use App\Services\BankReconciliationSessionService;
use Illuminate\Http\RedirectResponse;

class BankReconciliationActionController extends Controller
{
    public function action(
        BankReconciliationActionRequest $request,
        BankReconciliationSession $bankReconciliationSession,
        string $action,
        BankReconciliationSessionService $service
    ): RedirectResponse {
        $this->authorize($action, $bankReconciliationSession);
        $service->action($bankReconciliationSession, $action, $request->validated());

        return back()->with('success', 'تم تحديث دورة جلسة المطابقة.');
    }

    public function reopen(
        BankReconciliationReopenRequest $request,
        BankReconciliationSession $bankReconciliationSession,
        BankReconciliationReopenService $service
    ): RedirectResponse {
        $this->authorize('reopen', $bankReconciliationSession);
        $service->reopen($bankReconciliationSession, $request->validated('reason'));

        return back()->with('success', 'تم إعادة فتح جلسة المطابقة مع حفظ التاريخ.');
    }

    public function suggest(
        BankReconciliationSession $bankReconciliationSession,
        BankMatchingSuggestionService $service
    ): RedirectResponse {
        $this->authorize('match', $bankReconciliationSession);
        $service->suggest($bankReconciliationSession, 40, request()->boolean('auto_match'));

        return back()->with('success', 'تم توليد اقتراحات مطابقة قابلة للتفسير.');
    }
}
