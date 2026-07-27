<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\YearEndClosingSettingsRequest;
use App\Models\Account;
use App\Models\YearEndClosingSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;

class AccountingClosingSettingsController extends Controller
{
    public function update(YearEndClosingSettingsRequest $request, TenantContext $tenant, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.closing_settings.update'), 403);
        $data = $request->validated();
        $accountIds = array_filter(collect($data)->only([
            'income_summary_account_id', 'retained_earnings_account_id', 'current_year_profit_account_id',
            'opening_balance_equity_account_id',
        ])->all());
        if (Account::where('company_id', $tenant->companyId())->where('is_posting', true)
            ->where('is_active', true)->whereIn('id', $accountIds)->count() !== count($accountIds)) {
            throw new BusinessRuleException('Closing accounts must be active tenant posting accounts.');
        }
        $settings = YearEndClosingSetting::query()->updateOrCreate(['company_id' => $tenant->companyId()], $data);
        $audit->record('closing_settings.updated', $settings);

        return back()->with('success', 'تم تحديث إعدادات الإقفال.');
    }
}
