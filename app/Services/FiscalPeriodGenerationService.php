<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FiscalPeriodGenerationService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function monthly(FiscalYear $year): Collection
    {
        if ($year->company_id !== $this->tenant->companyId() || $year->status === 'locked') {
            throw new BusinessRuleException('Fiscal year cannot be changed.');
        }

        return DB::transaction(function () use ($year) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $existing = $year->periods()->where('is_adjustment_period', false)->lockForUpdate()->get();
            if ($existing->isNotEmpty()) {
                app(AccountingPeriodService::class)->assertCompleteCoverage($year);

                return $existing;
            }
            $cursor = $year->start_date->copy();
            $number = 1;
            while ($cursor->lte($year->end_date)) {
                $end = $cursor->copy()->endOfMonth()->min($year->end_date);
                $period = new AccountingPeriod;
                $period->forceFill([
                    'company_id' => $year->company_id, 'fiscal_year_id' => $year->id,
                    'period_number' => $number, 'code' => $year->code.'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'name' => $cursor->translatedFormat('F Y'), 'start_date' => $cursor,
                    'end_date' => $end, 'status' => 'open', 'is_adjustment_period' => false,
                ])->save();
                $cursor = $end->copy()->addDay();
                $number++;
            }

            return $year->periods()->get();
        });
    }
}
