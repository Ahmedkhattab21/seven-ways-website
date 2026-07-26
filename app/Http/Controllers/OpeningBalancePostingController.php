<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalEntryReversalRequest;
use App\Http\Requests\OpeningBalancePostingRequest;
use App\Models\OpeningBalanceDocument;
use App\Services\AccountingPostingService;
use Illuminate\Http\RedirectResponse;

class OpeningBalancePostingController extends Controller
{
    public function __construct(private AccountingPostingService $service)
    {
    }

    public function post(OpeningBalancePostingRequest $request, OpeningBalanceDocument $openingBalance): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.opening_balances.post'), 403);
        $entry = $this->service->post($openingBalance, $request->validated());

        return redirect()->route('accounting.journals.show', $entry)->with('success', 'Opening balance posted.');
    }

    public function reverse(JournalEntryReversalRequest $request, OpeningBalanceDocument $openingBalance): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.opening_balances.reverse'), 403);
        $entry = $this->service->reverse($openingBalance, (string) $request->string('reason'), $request->input('posting_date'));

        return redirect()->route('accounting.journals.show', $entry)->with('success', 'Opening balance reversed.');
    }
}
