<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalEntryActionRequest;
use App\Http\Requests\JournalEntryReversalRequest;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\RedirectResponse;

class JournalEntryActionController extends Controller
{
    public function __construct(private JournalEntryService $service)
    {
    }

    public function action(JournalEntryActionRequest $request, JournalEntry $journalEntry, string $action): RedirectResponse
    {
        $this->authorize($action, $journalEntry);
        $entry = $this->service->action($journalEntry, $action);

        return back()->with('success', "Journal {$entry->status}.");
    }

    public function reverse(JournalEntryReversalRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('reverse', $journalEntry);
        $reversal = $this->service->reverse($journalEntry, (string) $request->string('reason'), $request->input('posting_date'));

        return redirect()->route('accounting.journals.show', $reversal)->with('success', 'Reversal posted.');
    }
}
