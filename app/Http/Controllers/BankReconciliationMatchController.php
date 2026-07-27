<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliationActionRequest;
use App\Http\Requests\BankReconciliationMatchRequest;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationSession;
use App\Services\BankReconciliationMatchingService;
use Illuminate\Http\RedirectResponse;

class BankReconciliationMatchController extends Controller
{
    public function store(
        BankReconciliationMatchRequest $request,
        BankReconciliationSession $bankReconciliationSession,
        BankReconciliationMatchingService $service
    ): RedirectResponse {
        $this->authorize('match', $bankReconciliationSession);
        $service->createManualMatch(
            $bankReconciliationSession, $request->validated('statement'), $request->validated('book')
        );

        return back()->with('success', 'تم حفظ المطابقة اليدوية.');
    }

    public function action(
        BankReconciliationActionRequest $request,
        BankReconciliationMatch $bankReconciliationMatch,
        string $action,
        BankReconciliationMatchingService $service
    ): RedirectResponse {
        $this->authorize('update', $bankReconciliationMatch);
        match ($action) {
            'accept' => $service->acceptSuggestedMatch($bankReconciliationMatch),
            'reject' => $service->rejectSuggestedMatch($bankReconciliationMatch),
            'unmatch' => $service->unmatch($bankReconciliationMatch, (string) $request->validated('reason')),
            default => abort(404),
        };

        return back()->with('success', 'تم تحديث المطابقة.');
    }
}
