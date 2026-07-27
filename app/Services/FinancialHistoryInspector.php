<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialHistoryInspector
{
    public function hasPostedFinancialMovements(Company $company): bool
    {
        return $this->summary($company)['posted_records'] > 0;
    }

    public function summary(Company $company): array
    {
        $summary = [
            'posted_records' => 0,
            'sar_documents' => 0,
            'sar_journals' => 0,
            'opening_balances' => 0,
            'vat_15_lines' => 0,
            'first_movement_date' => null,
            'last_movement_date' => null,
            'currency_usage' => [],
        ];

        $this->addCurrencyUsage(
            $summary,
            'journal_entries',
            fn (Builder $query) => $query->where('company_id', $company->id)
                ->whereIn('status', ['posted', 'reversed']),
            'entry_date',
            true
        );
        $this->addCurrencyUsage(
            $summary,
            'sales_invoices',
            fn (Builder $query) => $query->where('company_id', $company->id)
                ->whereNotIn('status', ['draft', 'cancelled', 'voided']),
            'invoice_date'
        );
        $this->addCurrencyUsage(
            $summary,
            'supplier_invoices',
            fn (Builder $query) => $query->where('company_id', $company->id)
                ->whereIn('status', ['posted', 'partially_paid', 'paid', 'credited', 'overdue']),
            'invoice_date'
        );
        $this->addOpeningBalanceUsage($summary, $company);

        $summary['vat_15_lines'] = $this->taxRateUsage($company, 'sales_invoice_items', 'sales_invoices', 'sales_invoice_id')
            + $this->taxRateUsage($company, 'supplier_invoice_items', 'supplier_invoices', 'supplier_invoice_id');

        ksort($summary['currency_usage']);

        return $summary;
    }

    private function addCurrencyUsage(
        array &$summary,
        string $table,
        callable $scope,
        string $dateColumn,
        bool $journal = false
    ): void {
        if (! $this->hasColumns($table, ['company_id', 'currency_id', 'status', $dateColumn])
            || ! $this->hasColumns('currencies', ['id', 'code'])) {
            return;
        }

        $query = $scope(DB::table($table));
        $rows = (clone $query)
            ->join('currencies', 'currencies.id', '=', $table.'.currency_id')
            ->select('currencies.code', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('currencies.code')
            ->pluck('aggregate', 'currencies.code');

        foreach ($rows as $code => $count) {
            $count = (int) $count;
            $summary['posted_records'] += $count;
            $summary['currency_usage'][$code] = ($summary['currency_usage'][$code] ?? 0) + $count;
            if ($code === 'SAR') {
                $summary[$journal ? 'sar_journals' : 'sar_documents'] += $count;
            }
        }

        $first = (clone $query)->min($dateColumn);
        $last = (clone $query)->max($dateColumn);
        if ($first && (! $summary['first_movement_date'] || $first < $summary['first_movement_date'])) {
            $summary['first_movement_date'] = $first;
        }
        if ($last && (! $summary['last_movement_date'] || $last > $summary['last_movement_date'])) {
            $summary['last_movement_date'] = $last;
        }
    }

    private function addOpeningBalanceUsage(array &$summary, Company $company): void
    {
        $table = 'opening_balance_documents';
        if (! $this->hasColumns($table, ['company_id', 'status', 'balance_date'])) {
            return;
        }

        $query = DB::table($table)
            ->where('company_id', $company->id)
            ->whereIn('status', ['posted', 'reversed']);
        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }

        $currencyCode = $company->currency?->code ?? $company->currency_code ?: 'UNKNOWN';
        $summary['opening_balances'] += $count;
        $summary['posted_records'] += $count;
        $summary['currency_usage'][$currencyCode] = ($summary['currency_usage'][$currencyCode] ?? 0) + $count;
        if ($currencyCode === 'SAR') {
            $summary['sar_documents'] += $count;
        }

        $first = (clone $query)->min('balance_date');
        $last = (clone $query)->max('balance_date');
        if ($first && (! $summary['first_movement_date'] || $first < $summary['first_movement_date'])) {
            $summary['first_movement_date'] = $first;
        }
        if ($last && (! $summary['last_movement_date'] || $last > $summary['last_movement_date'])) {
            $summary['last_movement_date'] = $last;
        }
    }

    private function taxRateUsage(
        Company $company,
        string $lineTable,
        string $documentTable,
        string $foreignKey
    ): int {
        if (! $this->hasColumns($lineTable, [$foreignKey, 'tax_rate'])
            || ! $this->hasColumns($documentTable, ['id', 'company_id', 'status'])) {
            return 0;
        }

        return DB::table($lineTable)
            ->join($documentTable, $documentTable.'.id', '=', $lineTable.'.'.$foreignKey)
            ->where($documentTable.'.company_id', $company->id)
            ->when(
                $documentTable === 'sales_invoices',
                fn (Builder $query) => $query->whereNotIn($documentTable.'.status', ['draft', 'cancelled', 'voided']),
                fn (Builder $query) => $query->whereIn(
                    $documentTable.'.status',
                    ['posted', 'partially_paid', 'paid', 'credited', 'overdue']
                )
            )
            ->where($lineTable.'.tax_rate', 15)
            ->count();
    }

    private function hasColumns(string $table, array $columns): bool
    {
        return Schema::hasTable($table) && Schema::hasColumns($table, $columns);
    }
}
