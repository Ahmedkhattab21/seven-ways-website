<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\FiscalPeriodGenerationService;
use App\Services\FiscalYearService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairUatFiscalYear extends Command
{
    protected $signature = 'uat:repair-fiscal-year {code}';

    protected $description = 'Repair an empty UAT fiscal year through the official lifecycle';

    public function handle(TenantContext $tenant, FiscalPeriodGenerationService $periods, FiscalYearService $years): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }

        $year = FiscalYear::query()->with('company')->where('code', $this->argument('code'))->first();
        if (! $year || $year->company?->name !== 'Seven Ways') {
            $this->error('STOP — Fiscal year must belong to the Seven Ways company.');

            return self::FAILURE;
        }

        $periodCount = $year->periods()->where('is_adjustment_period', false)->count();
        if ($this->hasFinancialData($year->id)) {
            $this->error('STOP — Fiscal year contains periods or financial data; no changes were made.');

            return self::FAILURE;
        }
        if ($periodCount > 0) {
            try {
                app(AccountingPeriodService::class)->assertCompleteCoverage($year);
            } catch (\Throwable) {
                $this->error('STOP — Existing periods do not provide complete coverage; no changes were made.');

                return self::FAILURE;
            }
            if ($year->status === 'open' && $year->is_current) {
                $this->info(sprintf('READY — %s already has %d periods and is open.', $year->code, $periodCount));

                return self::SUCCESS;
            }
            $this->error('STOP — Existing fiscal-year lifecycle is not the expected final state; no changes were made.');

            return self::FAILURE;
        }

        $actor = User::query()->where('company_id', $year->company_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_owner'))
            ->where('status', 'active')->first();
        if (! $actor) {
            $this->error('STOP — No active company owner is available for audited repair.');

            return self::FAILURE;
        }

        $tenant->initialize($actor);
        DB::transaction(function () use ($year): void {
            $locked = FiscalYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => 'draft', 'is_current' => false,
                'opened_by' => null, 'opened_at' => null,
                'reopened_by' => null, 'reopened_at' => null,
            ])->save();
        });

        $year = $year->fresh();
        $periods->monthly($year);
        $opened = $years->open($year->fresh(), $actor);

        $this->info(sprintf(
            'READY — %s has %d periods and is %s through the official lifecycle.',
            $opened->code,
            $opened->periods()->where('is_adjustment_period', false)->count(),
            $opened->status
        ));

        return self::SUCCESS;
    }

    private function hasFinancialData(int $fiscalYearId): bool
    {
        foreach ([
            ['journal_entries', 'fiscal_year_id'],
            ['opening_balance_documents', 'fiscal_year_id'],
            ['accounting_closing_runs', 'fiscal_year_id'],
            ['accounting_module_locks', 'fiscal_year_id'],
            ['bank_reconciliation_sessions', 'fiscal_year_id'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $fiscalYearId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
