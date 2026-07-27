<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduledJournalReversalRequest;
use App\Models\AccountingAdjustment;
use App\Models\ScheduledJournalReversal;
use App\Services\ScheduledJournalReversalService;
use Illuminate\Http\RedirectResponse;

class ScheduledJournalReversalController extends Controller
{
    public function store(ScheduledJournalReversalRequest $request, AccountingAdjustment $accountingAdjustment, ScheduledJournalReversalService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.adjustments.schedule_reversal'), 403);
        $service->schedule($accountingAdjustment->journalEntry, $request->validated('scheduled_date'));

        return back()->with('success', 'تمت جدولة العكس.');
    }

    public function process(ScheduledJournalReversal $scheduledJournalReversal, ScheduledJournalReversalService $service): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('accounting.adjustments.post'), 403);
        $service->process($scheduledJournalReversal);

        return back()->with('success', 'تمت معالجة العكس المجدول.');
    }
}
