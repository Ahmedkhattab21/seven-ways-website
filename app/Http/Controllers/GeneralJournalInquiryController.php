<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralJournalInquiryRequest;
use App\Services\FinancialReportViewDataService;
use App\Services\GeneralJournalInquiryService;

class GeneralJournalInquiryController extends Controller
{
    public function __invoke(GeneralJournalInquiryRequest $request, GeneralJournalInquiryService $service, FinancialReportViewDataService $viewData)
    {
        abort_unless($request->user()->hasPermission('accounting.general_journal.view'), 403);

        return view('accounting.reports.general-journal', ['entries' => $service->report($request->validated())] + $viewData->filters());
    }
}
