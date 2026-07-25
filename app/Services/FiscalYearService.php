<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    public function save(FiscalYear $fiscalYear, int $companyId, User $actor, array $data): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $companyId, $actor, $data) {
            $years = FiscalYear::query()->where('company_id', $companyId)->lockForUpdate()->get();

            $overlap = $years->where('id', '!=', $fiscalYear->getKey())->contains(
                fn (FiscalYear $year) => $data['start_date'] <= $year->end_date->toDateString()
                    && $data['end_date'] >= $year->start_date->toDateString()
            );
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'الفترة تتداخل مع سنة مالية موجودة.']);
            }

            if ($data['is_current'] ?? false) {
                FiscalYear::query()->where('company_id', $companyId)
                    ->whereKeyNot($fiscalYear->getKey())->update(['is_current' => false]);
            }

            $fiscalYear->forceFill([
                ...$data,
                'company_id' => $companyId,
                'created_by' => $fiscalYear->created_by ?: $actor->id,
            ])->save();

            return $fiscalYear;
        });
    }
}
