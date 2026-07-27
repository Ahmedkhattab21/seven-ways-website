<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\TreasuryTransferActionRequest;
use App\Http\Requests\TreasuryTransferProcessingRequest;
use App\Http\Requests\TreasuryTransferRequest;
use App\Http\Requests\TreasuryTransferReversalRequest;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\Currency;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryTransferProcessingService;
use App\Services\TreasuryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TreasuryTransferController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.transfers.view'), 403);

        return view('treasury.transfers', [
            'transfers' => TreasuryTransfer::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->latest('id')->get(),
            'bankAccounts' => BankAccount::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'cashBoxes' => CashBox::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->where('status', 'active')->get(),
            'branches' => $tenant->accessibleBranches(),
            'currencies' => Currency::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(TreasuryTransferRequest $request, TreasuryTransferService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.transfers.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء التحويل كمسودة بدون ترحيل محاسبي.');
    }

    public function update(
        TreasuryTransferRequest $request,
        TreasuryTransfer $treasuryTransfer,
        TreasuryTransferService $service
    ): RedirectResponse {
        $this->authorize('update', $treasuryTransfer);
        $service->update($treasuryTransfer, $request->validated());

        return back()->with('success', 'تم تحديث مسودة التحويل.');
    }

    public function action(
        TreasuryTransferActionRequest $request,
        TreasuryTransfer $treasuryTransfer,
        string $action,
        TreasuryTransferService $service
    ): RedirectResponse {
        $permission = 'treasury.transfers.'.$action;
        abort_unless($request->user()->hasPermission($permission), 403);
        if ($action === 'approve') {
            $this->authorize('approve', $treasuryTransfer);
        }
        $service->action($treasuryTransfer, $action, $request->validated('reason', ''));

        return back()->with('success', 'تم تحديث دورة اعتماد التحويل.');
    }

    public function process(
        TreasuryTransferProcessingRequest $request,
        TreasuryTransfer $treasuryTransfer,
        TreasuryTransferProcessingService $service
    ): RedirectResponse {
        $this->authorize('process', $treasuryTransfer);
        $service->process($treasuryTransfer);

        return back()->with('success', 'تم تنفيذ وترحيل التحويل.');
    }

    public function reverse(
        TreasuryTransferReversalRequest $request,
        TreasuryTransfer $treasuryTransfer,
        TreasuryTransferProcessingService $service
    ): RedirectResponse {
        $this->authorize('reverse', $treasuryTransfer);
        $service->reverse($treasuryTransfer, $request->validated('reason'), $request->validated('date'));

        return back()->with('success', 'تم عكس التحويل بقيد معاكس.');
    }
}
