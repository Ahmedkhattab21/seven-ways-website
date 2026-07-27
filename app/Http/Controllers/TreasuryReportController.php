<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use App\Models\CashOverShortAdjustment;
use App\Models\CashPayment;
use App\Models\CashReceipt;
use App\Models\Cheque;
use App\Models\MerchantSettlement;
use App\Models\TreasuryTransfer;
use Illuminate\View\View;

class TreasuryReportController extends Controller
{
    public function __invoke(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.reports.view'), 403);
        $companyId = $tenant->companyId();
        $branches = $tenant->accessibleBranches()->pluck('id');

        return view('treasury.operation-reports', [
            'openSessions' => CashBoxSession::query()->where('company_id', $companyId)
                ->whereIn('branch_id', $branches)->whereNotIn('status', ['closed', 'cancelled'])->get(),
            'counts' => CashBoxCount::query()->where('company_id', $companyId)->latest('id')->limit(50)->get(),
            'differences' => CashOverShortAdjustment::query()->where('company_id', $companyId)->latest('id')->limit(50)->get(),
            'transfers' => TreasuryTransfer::query()->where('company_id', $companyId)
                ->whereIn('branch_id', $branches)->latest('id')->limit(50)->get(),
            'cheques' => Cheque::query()->where('company_id', $companyId)
                ->whereIn('branch_id', $branches)->orderBy('due_date')->limit(100)->get(),
            'settlements' => MerchantSettlement::query()->where('company_id', $companyId)->latest('id')->limit(50)->get(),
            'pendingCount' => TreasuryTransfer::query()->where('company_id', $companyId)
                ->whereIn('status', ['pending_approval', 'approved', 'processing', 'failed'])->count()
                + CashReceipt::query()->where('company_id', $companyId)->whereIn('status', ['pending_approval', 'approved'])->count()
                + CashPayment::query()->where('company_id', $companyId)->whereIn('status', ['pending_approval', 'approved'])->count(),
        ]);
    }
}
