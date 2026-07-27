<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountingAdjustmentRequest;
use App\Models\AccountingAdjustment;
use App\Services\AccountingAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingAdjustmentController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('accounting.adjustments.view'), 403);

        return view('accounting.closing.adjustments', [
            'adjustments' => AccountingAdjustment::where('company_id', $tenant->companyId())->with('journalEntry')->latest()->paginate(50),
        ]);
    }

    public function store(AccountingAdjustmentRequest $request, AccountingAdjustmentService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.adjustments.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء قيد التسوية.');
    }
}
