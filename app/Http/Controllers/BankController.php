<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankRequest;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.banks.view'), 403);

        return view('treasury.banks', [
            'banks' => Bank::query()->where(fn ($query) => $query->whereNull('company_id')
                ->orWhere('company_id', $tenant->companyId()))->orderBy('name_ar')->get(),
        ]);
    }

    public function store(BankRequest $request, BankService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.banks.manage'), 403);
        $service->save($request->validated());

        return back()->with('success', 'تمت إضافة البنك.');
    }

    public function update(BankRequest $request, Bank $bank, BankService $service): RedirectResponse
    {
        $this->authorize('update', $bank);
        $service->save($request->validated(), $bank);

        return back()->with('success', 'تم تحديث البنك.');
    }

    public function disable(Bank $bank, BankService $service): RedirectResponse
    {
        $this->authorize('update', $bank);
        $service->disable($bank);

        return back()->with('success', 'تم تعطيل البنك.');
    }
}
