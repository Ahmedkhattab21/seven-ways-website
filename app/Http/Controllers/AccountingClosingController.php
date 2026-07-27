<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingClosingException;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\ScheduledJournalReversal;
use Illuminate\View\View;

class AccountingClosingController extends Controller
{
    public function __invoke(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('accounting.closing.view'), 403);
        $companyId = $tenant->companyId();

        return view('accounting.closing.index', [
            'currentYear' => FiscalYear::where('company_id', $companyId)->where('is_current', true)->first(),
            'periods' => AccountingPeriod::where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'runs' => AccountingClosingRun::where('company_id', $companyId)->latest()->limit(20)->get(),
            'blockingExceptions' => AccountingClosingException::where('company_id', $companyId)->where('status', 'open')->count(),
            'scheduledReversals' => ScheduledJournalReversal::where('company_id', $companyId)->whereIn('status', ['scheduled', 'ready', 'failed'])->count(),
        ]);
    }
}
