<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\FiscalYear;

class NextFiscalYearService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getOrCreate(FiscalYear $year, bool $generatePeriods = true): FiscalYear
    {
        $start = $year->end_date->copy()->addDay();
        $days = $year->start_date->diffInDays($year->end_date);
        $end = $start->copy()->addDays($days);
        $next = FiscalYear::query()->where('company_id', $year->company_id)
            ->whereDate('start_date', $start)->first();
        if (! $next) {
            $next = new FiscalYear;
            $next->forceFill([
                'company_id' => $year->company_id, 'code' => 'FY-'.$start->format('Y'),
                'name' => 'Fiscal Year '.$start->format('Y'), 'start_date' => $start, 'end_date' => $end,
                'status' => 'draft', 'is_current' => false, 'created_by' => $this->tenant->user()->id,
            ])->save();
        }
        if ($generatePeriods && $next->periods()->where('is_adjustment_period', false)->doesntExist()) {
            app(FiscalPeriodGenerationService::class)->monthly($next);
        }

        return $next->fresh('periods');
    }
}
