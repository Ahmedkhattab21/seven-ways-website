<?php

namespace App\Console\Commands;

use App\Models\AccountingSetting;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Tax;
use App\Services\FinancialHistoryInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuditEgyptLocalization extends Command
{
    protected $signature = 'localization:audit-egypt {--apply-safe-defaults : Apply defaults only to companies without posted financial history}';

    protected $description = 'Audit Egypt localization without changing financial documents or historical values';

    public function handle(FinancialHistoryInspector $history): int
    {
        if ($this->option('apply-safe-defaults') && app()->environment('production')) {
            $this->error('Applying Egypt defaults is disabled in production. Use a reviewed migration process.');

            return self::FAILURE;
        }

        $egp = Currency::query()->where('code', 'EGP')->where('is_active', true)->first();
        if (! $egp) {
            throw new RuntimeException('Active EGP currency is required. Run ReferenceDataSeeder first.');
        }

        $rows = [];
        Company::query()->with('currency')->orderBy('id')->each(function (Company $company) use ($history, $egp, &$rows) {
            $summary = $history->summary($company);
            $applied = false;
            if ($this->option('apply-safe-defaults') && $summary['posted_records'] === 0) {
                $applied = $this->applySafeDefaults($company->id, $egp, $history);
            }
            $rows[] = [
                $company->id,
                $company->name,
                $company->country_code,
                $company->currency?->code ?? $company->currency_code,
                $summary['posted_records'],
                $summary['sar_documents'],
                $summary['sar_journals'],
                $summary['opening_balances'] ?? 0,
                collect($summary['currency_usage'])->map(
                    fn ($count, $code) => $code.':'.$count
                )->implode(', ') ?: '—',
                $summary['vat_15_lines'],
                $summary['first_movement_date'] ?: '—',
                $summary['last_movement_date'] ?: '—',
                $applied
                    ? 'applied'
                    : ($summary['posted_records'] || $this->option('apply-safe-defaults')
                        ? 'review required'
                        : 'read-only'),
            ];
        });

        $this->table(
            ['ID', 'Company', 'Country', 'Base currency', 'Posted', 'SAR docs', 'SAR journals', 'Opening balances', 'Currencies', 'VAT 15 lines', 'First', 'Last', 'Result'],
            $rows
        );
        $this->info($this->option('apply-safe-defaults')
            ? 'Safe defaults were applied only where no posted financial history exists.'
            : 'Read-only audit completed. No data was changed.');

        return self::SUCCESS;
    }

    private function applySafeDefaults(
        int $companyId,
        Currency $egp,
        FinancialHistoryInspector $history
    ): bool {
        return DB::transaction(function () use ($companyId, $egp, $history): bool {
            $company = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            if ($history->hasPostedFinancialMovements($company)) {
                $this->warn("Company {$company->id}: financial history appeared while locked; no defaults were changed.");

                return false;
            }

            $vat = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->first() ?? new Tax;
            $vat->forceFill([
                'company_id' => $company->id,
                'code' => 'VAT14-EG',
                'name' => 'ضريبة القيمة المضافة المصرية 14%',
                'rate' => 14,
                'tax_type' => 'both',
                'is_default' => false,
                'is_inclusive' => false,
                'is_active' => true,
            ])->save();
            $company->forceFill([
                'country_code' => 'EG',
                'currency_id' => $egp->id,
                'currency_code' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'default_language' => 'ar',
                'ui_direction' => 'rtl',
                'default_tax_id' => $company->default_tax_id ?: $vat->id,
            ])->save();
            AccountingSetting::query()->where('company_id', $company->id)
                ->update(['base_currency_id' => $egp->id]);
            $this->line("Company {$company->id}: applied EG, EGP, Africa/Cairo and safe tax reference defaults.");

            return true;
        });
    }
}
