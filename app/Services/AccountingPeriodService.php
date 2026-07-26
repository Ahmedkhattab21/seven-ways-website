<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountingPeriodOpened;
use App\Events\AccountingPeriodReopened;
use App\Events\AccountingPeriodSoftClosed;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(AccountingPeriod $period, array $data): AccountingPeriod
    {
        $year = FiscalYear::query()->whereKey($data['fiscal_year_id'])
            ->where('company_id', $this->tenant->companyId())->firstOrFail();
        if ($year->status === 'locked') {
            throw new BusinessRuleException('Locked fiscal years cannot be changed.');
        }
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($end->lt($start) || $start->lt($year->start_date) || $end->gt($year->end_date)) {
            throw new BusinessRuleException('Accounting period must be inside the fiscal year.');
        }
        $overlap = AccountingPeriod::query()->where('fiscal_year_id', $year->id)
            ->whereKeyNot($period->id)->where('is_adjustment_period', false)
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap && empty($data['is_adjustment_period'])) {
            throw new BusinessRuleException('Accounting periods cannot overlap.');
        }
        $period->forceFill($data + ['company_id' => $year->company_id, 'status' => $period->status ?: 'open'])->save();

        return $period;
    }

    public function transition(AccountingPeriod $period, string $action, string $reason): AccountingPeriod
    {
        $map = [
            'open' => [['soft_closed', 'closed'], 'open', AccountingPeriodOpened::class],
            'soft_close' => [['open'], 'soft_closed', AccountingPeriodSoftClosed::class],
            'reopen' => [['soft_closed', 'closed'], 'open', AccountingPeriodReopened::class],
            'lock' => [['closed'], 'locked', null],
        ];
        if (! isset($map[$action])) {
            throw new BusinessRuleException('Unsupported accounting period action.');
        }

        return DB::transaction(function () use ($period, $reason, $map, $action) {
            $period = AccountingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->company_id !== $this->tenant->companyId() || ! in_array($period->status, $map[$action][0], true)) {
                throw new BusinessRuleException('Invalid accounting period transition.');
            }
            $to = $map[$action][1];
            $period->forceFill([
                'status' => $to, 'close_reason' => $reason,
                'closed_by' => $to === 'open' ? null : $this->tenant->user()->id,
                'closed_at' => $to === 'open' ? null : now(),
                'reopened_by' => $to === 'open' ? $this->tenant->user()->id : null,
                'reopened_at' => $to === 'open' ? now() : null,
            ])->save();
            $this->audit->record('accounting_period.'.$action, $period, ['reason' => $reason]);
            if ($map[$action][2]) {
                $event = $map[$action][2];
                DB::afterCommit(fn () => event(new $event($period->id)));
            }

            return $period;
        });
    }

    public function assertCompleteCoverage(FiscalYear $year): void
    {
        $periods = $year->periods()->where('is_adjustment_period', false)->orderBy('start_date')->get();
        if ($periods->isEmpty() || ! $periods->first()->start_date->equalTo($year->start_date)
            || ! $periods->last()->end_date->equalTo($year->end_date)) {
            throw new BusinessRuleException('Periods must cover the complete fiscal year.');
        }
        $expected = $year->start_date->copy();
        foreach ($periods as $period) {
            if (! $period->start_date->equalTo($expected)) {
                throw new BusinessRuleException('Accounting periods cannot contain gaps.');
            }
            $expected = $period->end_date->copy()->addDay();
        }
    }

    public function periodForDate(string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()->where('company_id', $this->tenant->companyId())
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->where('is_adjustment_period', false)->first();
    }
}
