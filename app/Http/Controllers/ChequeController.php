<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ChequeActionRequest;
use App\Http\Requests\ChequeBounceRequest;
use App\Http\Requests\ChequeEndorsementRequest;
use App\Http\Requests\ChequeRequest;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\ChequeEndorsement;
use App\Services\ChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChequeController extends Controller
{
    public function received(TenantContext $tenant): View
    {
        return $this->index($tenant, 'received');
    }

    public function issued(TenantContext $tenant): View
    {
        return $this->index($tenant, 'issued');
    }

    public function store(ChequeRequest $request, ChequeService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cheques.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم تسجيل الشيك مع إخفاء الرقم الحساس.');
    }

    public function action(
        ChequeActionRequest $request,
        Cheque $cheque,
        string $action,
        ChequeService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cheques.'.$action), 403);
        if ($action === 'replace') {
            $service->replace($cheque, $request->validated());
        } else {
            $service->action($cheque, $action, $request->validated());
        }

        return back()->with('success', 'تم تحديث دورة الشيك.');
    }

    public function bounce(
        ChequeBounceRequest $request,
        Cheque $cheque,
        ChequeService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cheques.bounce'), 403);
        $service->action($cheque, 'bounce', $request->validated());

        return back()->with('success', 'تم تسجيل ارتداد الشيك بقيد منفصل.');
    }

    public function endorse(
        ChequeEndorsementRequest $request,
        Cheque $cheque,
        ChequeService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cheques.endorse'), 403);
        $service->endorse($cheque, $request->validated());

        return back()->with('success', 'تم إنشاء طلب تظهير بدون ترحيل محاسبي.');
    }

    public function approveEndorsement(
        ChequeEndorsement $chequeEndorsement,
        ChequeService $service
    ): RedirectResponse {
        $this->authorize('approve', $chequeEndorsement);
        $service->approveEndorsement($chequeEndorsement);

        return back()->with('success', 'تم اعتماد التظهير كأساس تشغيلي بدون قيد.');
    }

    private function index(TenantContext $tenant, string $direction): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.cheques.view'), 403);

        return view('treasury.cheques', [
            'direction' => $direction,
            'cheques' => Cheque::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('direction', $direction)->with('histories')->latest('id')->paginate(30),
            'banks' => Bank::query()->where(fn ($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
            'bankAccounts' => BankAccount::query()->where('company_id', $tenant->companyId())
                ->where('status', 'active')->get(),
            'accounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)->where('is_posting', true)->get(),
            'branches' => $tenant->accessibleBranches(), 'company' => $tenant->company(),
        ]);
    }
}
