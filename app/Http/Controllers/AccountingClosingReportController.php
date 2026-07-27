<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingAdjustment;
use App\Models\AccountingClosingException;
use App\Models\AccountingClosingRun;
use App\Models\ScheduledJournalReversal;
use Illuminate\View\View;

class AccountingClosingReportController extends Controller
{
    public function __invoke(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('accounting.closing_reports.view'), 403);
        $companyId = $tenant->companyId();

        return view('accounting.closing.reports', [
            'runs' => AccountingClosingRun::where('company_id', $companyId)->latest()->get(),
            'exceptions' => AccountingClosingException::where('company_id', $companyId)->latest()->get(),
            'adjustments' => AccountingAdjustment::where('company_id', $companyId)->latest()->get(),
            'reversals' => ScheduledJournalReversal::where('company_id', $companyId)->latest()->get(),
        ]);
    }
}
