<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountingSettingsUpdated;
use App\Models\AccountingSetting;
use App\Models\Currency;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class AccountingSettingsService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function update(array $data): AccountingSetting
    {
        $companyId = $this->tenant->companyId();
        Currency::query()->whereKey($data['base_currency_id'])->where('is_active', true)->firstOrFail();
        if (! empty($data['current_fiscal_year_id'])) {
            FiscalYear::query()->whereKey($data['current_fiscal_year_id'])->where('company_id', $companyId)->firstOrFail();
        }

        return DB::transaction(function () use ($companyId, $data) {
            $settings = AccountingSetting::query()->where('company_id', $companyId)->lockForUpdate()->firstOrFail();
            if ($settings->base_currency_id !== (int) $data['base_currency_id']
                && \App\Models\OpeningBalanceDocument::query()->where('company_id', $companyId)->exists()) {
                throw new BusinessRuleException('Base currency cannot change after accounting documents exist.');
            }
            $settings->fill($data);
            $settings->forceFill([
                'auto_post_sales' => false, 'auto_post_purchases' => false,
                'auto_post_inventory' => false, 'auto_post_payments' => false,
            ])->save();
            $this->audit->record('accounting_settings.updated', $settings);
            DB::afterCommit(fn () => event(new AccountingSettingsUpdated($settings->id)));

            return $settings;
        });
    }
}
