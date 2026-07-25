<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BranchSettingsRequest;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Services\BranchSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchSettingsController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        $branch = $tenant->branch();
        abort_unless($branch, 404);
        $settings = $branch->settings()->firstOrNew();
        $settings->setRelation('branch', $branch);

        abort_unless(
            auth()->user()->hasRole('system_admin')
            || auth()->user()->hasPermission('branch_settings.view'),
            403
        );

        $taxes = Tax::query()
            ->where('company_id', $tenant->companyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $paymentMethods = PaymentMethod::query()
            ->where('company_id', $tenant->companyId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('settings.branch', compact('branch', 'settings', 'taxes', 'paymentMethods'));
    }

    public function update(
        BranchSettingsRequest $request,
        TenantContext $tenant,
        BranchSettingsService $service
    ): RedirectResponse {
        $branch = $tenant->branch();
        abort_unless($branch, 404);
        $settings = $branch->settings()->firstOrNew();
        $settings->setRelation('branch', $branch);
        $this->authorize('update', $settings);

        $data = $request->validated();
        foreach ([
            'requires_discount_approval',
            'requires_invoice_cancel_approval',
            'allow_negative_stock',
        ] as $field) {
            $data[$field] = $request->boolean($field);
        }
        $service->update($branch, $data);

        return back()->with('status', 'تم تحديث إعدادات الفرع.');
    }
}
