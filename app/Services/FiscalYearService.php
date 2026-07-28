<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Events\FiscalYearCreated;
use App\Events\FiscalYearOpened;
use App\Events\FiscalYearReopened;
use App\Events\FiscalYearSoftClosed;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function save(FiscalYear $fiscalYear, int $companyId, User $actor, array $data): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $companyId, $actor, $data) {
            if ($fiscalYear->exists && ($fiscalYear->company_id !== $companyId || $fiscalYear->status !== 'draft')) {
                throw new BusinessRuleException('Only a draft fiscal year in the current company can be edited.');
            }
            if ($data['end_date'] < $data['start_date']) {
                throw new BusinessRuleException('Fiscal year end date must not precede its start date.');
            }
            $years = FiscalYear::query()->where('company_id', $companyId)->lockForUpdate()->get();
            if ($years->where('id', '!=', $fiscalYear->getKey())->contains(
                fn (FiscalYear $year) => $data['start_date'] <= $year->end_date->toDateString()
                    && $data['end_date'] >= $year->start_date->toDateString()
            )) {
                throw ValidationException::withMessages(['start_date' => 'Fiscal years cannot overlap.']);
            }
            if ($data['is_current'] ?? false) {
                FiscalYear::query()->where('company_id', $companyId)
                    ->whereKeyNot($fiscalYear->getKey())->update(['is_current' => false]);
            }
            $fiscalYear->forceFill([
                ...array_diff_key($data, ['status' => true]),
                'company_id' => $companyId,
                'code' => $data['code'] ?? 'FY-'.substr($data['start_date'], 0, 4),
                'status' => $fiscalYear->exists ? $fiscalYear->status : 'draft',
                'is_current' => $fiscalYear->exists ? (bool) ($data['is_current'] ?? $fiscalYear->is_current) : false,
                'created_by' => $fiscalYear->created_by ?: $actor->id,
            ])->save();
            $this->audit->recordAs(
                $fiscalYear->wasRecentlyCreated ? 'fiscal_year.created' : 'fiscal_year.updated',
                $fiscalYear,
                $companyId,
                $actor->id
            );
            if ($fiscalYear->wasRecentlyCreated) {
                DB::afterCommit(fn () => event(new FiscalYearCreated($fiscalYear->id)));
            }

            return $fiscalYear;
        });
    }

    public function open(FiscalYear $year, User $actor): FiscalYear
    {
        return DB::transaction(function () use ($year, $actor) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            if ($year->status !== 'draft') {
                throw new BusinessRuleException('Only a draft fiscal year can be opened.');
            }
            app(AccountingPeriodService::class)->assertCompleteCoverage($year);
            FiscalYear::query()->where('company_id', $year->company_id)->whereKeyNot($year->id)->update(['is_current' => false]);
            $year->forceFill([
                'status' => 'open', 'is_current' => true, 'opened_by' => $actor->id, 'opened_at' => now(),
            ])->save();
            $this->audit->record('fiscal_year.opened', $year);
            DB::afterCommit(fn () => event(new FiscalYearOpened($year->id)));

            return $year;
        });
    }

    public function softClose(FiscalYear $year, User $actor, string $reason): FiscalYear
    {
        return DB::transaction(function () use ($year, $actor, $reason) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            if ($year->status !== 'open') {
                throw new BusinessRuleException('Only an open fiscal year can be soft closed.');
            }
            $year->forceFill([
                'status' => 'soft_closed', 'is_current' => false, 'closed_by' => $actor->id,
                'closed_at' => now(), 'close_notes' => $reason,
            ])->save();
            $this->audit->record('fiscal_year.soft_closed', $year, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new FiscalYearSoftClosed($year->id)));

            return $year;
        });
    }

    public function reopen(FiscalYear $year, User $actor, string $reason): FiscalYear
    {
        return DB::transaction(function () use ($year, $actor, $reason) {
            $year = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            if (! in_array($year->status, ['soft_closed', 'closed'], true)) {
                throw new BusinessRuleException('Only a closed fiscal year can be reopened.');
            }
            $year->forceFill([
                'status' => 'open', 'reopened_by' => $actor->id, 'reopened_at' => now(), 'close_notes' => $reason,
            ])->save();
            $this->audit->record('fiscal_year.reopened', $year, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new FiscalYearReopened($year->id)));

            return $year;
        });
    }
}
