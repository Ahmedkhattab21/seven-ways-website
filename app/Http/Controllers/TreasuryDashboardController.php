<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryBalanceService;
use Illuminate\View\View;

class TreasuryDashboardController extends Controller
{
    public function __invoke(TenantContext $tenant, TreasuryBalanceService $balances): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.balances.view'), 403);
        $bankAccounts = BankAccount::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get();
        $cashBoxes = CashBox::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->where('status', 'active')->get();

        return view('treasury.dashboard', [
            'bankTotal' => $bankAccounts->reduce(fn ($sum, $account) => bcadd($sum, $balances->bank($account)['book_balance'], 4), '0.0000'),
            'cashTotal' => $cashBoxes->reduce(fn ($sum, $box) => bcadd($sum, $balances->cashBox($box)['book_balance'], 4), '0.0000'),
            'suspendedAccounts' => BankAccount::query()->where('company_id', $tenant->companyId())->where('status', 'suspended')->count(),
            'overLimitBoxes' => $cashBoxes->filter(function ($box) use ($balances) {
                return $box->maximum_cash_limit !== null
                    && bccomp($balances->cashBox($box)['book_balance'], (string) $box->maximum_cash_limit, 4) === 1;
            })->count(),
            'pendingTransfers' => TreasuryTransfer::query()->where('company_id', $tenant->companyId())
                ->whereIn('status', ['pending_approval', 'approved', 'ready_for_processing'])->count(),
            'lastReconciledDate' => $bankAccounts->max('last_reconciled_date'),
        ]);
    }
}
