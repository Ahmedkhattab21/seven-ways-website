<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CompanyUpdateRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        $company = $tenant->company();
        abort_unless($company, 404);
        $this->authorize('view', $company);

        return view('settings.company', compact('company'));
    }

    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->company_id === $company->id, 403);
        $data = [
            ...$request->safe()->except(['logo', 'is_active']),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($data);

        return back()->with('status', 'تم تحديث بيانات الشركة.');
    }
}
