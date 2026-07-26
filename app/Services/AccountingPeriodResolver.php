<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\AccountingSetting;
use App\Models\User;

class AccountingPeriodResolver
{
    public function resolve(int $companyId, string $date, string $module, User $actor, ?string $overrideReason = null): AccountingPeriod
    {
        $periods = AccountingPeriod::query()->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->where('is_adjustment_period', false)->get();
        if ($periods->count() !== 1) {
            throw new BusinessRuleException('Posting date must resolve to exactly one accounting period.');
        }
        $period = $periods->first();
        if (in_array($period->status, ['closed', 'locked'], true)) {
            throw new BusinessRuleException('The accounting period is closed or locked.');
        }
        if (in_array($module, $period->locked_modules ?? [], true)) {
            throw new BusinessRuleException('The source module is locked for this accounting period.');
        }
        if ($period->status === 'soft_closed') {
            $settings = AccountingSetting::query()->where('company_id', $companyId)->first();
            if (! $settings?->allow_posting_to_soft_closed_period
                || ! $actor->hasPermission('accounting.periods.post_soft_closed')
                || blank($overrideReason)) {
                throw new BusinessRuleException('Soft-closed period posting needs permission and a reason.');
            }
        }

        return $period;
    }
}
