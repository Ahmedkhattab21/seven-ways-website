<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CompanyUpdateRequest;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Tax;
use App\Services\CompanySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        $company = $tenant->company();
        abort_unless($company, 404);
        $this->authorize('view', $company);

        $currencies = Currency::query()->where('is_active', true)->orderBy('code')->get();
        $taxes = Tax::query()
            ->where('company_id', $tenant->companyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('settings.company', compact('company', 'currencies', 'taxes'));
    }

    public function update(
        CompanyUpdateRequest $request,
        Company $company,
        CompanySettingsService $service
    ): RedirectResponse {
        abort_unless($request->user()->company_id === $company->id, 403);
        $data = [
            ...$request->safe()->except(['logo', 'is_active']),
            'is_active' => $request->boolean('is_active'),
        ];
        $data['currency_code'] = Currency::query()->findOrFail($data['currency_id'])->code;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $service->update($company, $data);

        return back()->with('status', 'تم تحديث بيانات الشركة.');
    }
}
