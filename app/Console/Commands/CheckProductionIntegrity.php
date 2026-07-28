<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckProductionIntegrity extends Command
{
    protected $signature = 'production:check-integrity';

    protected $description = 'Read-only accounting, posting-link, stock, and tenant integrity checks';

    public function handle(): int
    {
        $checks = [
            'unbalanced_posted_journals' => $this->countWhen('journal_entries', fn () => DB::table('journal_entries')
                ->where('status', 'posted')
                ->whereRaw('ABS(base_total_debit - base_total_credit) > 0.0001')
                ->count()),
            'posted_links_without_journal' => $this->countWhen('accounting_posting_links', fn () => DB::table('accounting_posting_links')
                ->where('status', 'posted')->whereNull('journal_entry_id')->count()),
            'journal_lines_without_journal' => $this->countWhen('journal_entry_lines', fn () => DB::table('journal_entry_lines as lines')
                ->leftJoin('journal_entries as journals', 'journals.id', '=', 'lines.journal_entry_id')
                ->whereNull('journals.id')->count()),
            'stock_balance_formula_mismatch' => $this->countWhen('stock_balances', fn () => DB::table('stock_balances')
                ->whereRaw('ABS(available_quantity - (quantity - reserved_quantity)) > 0.000001')
                ->count()),
            'stock_balance_invalid_reservation' => $this->countWhen('stock_balances', fn () => DB::table('stock_balances')
                ->where(fn ($query) => $query->where('reserved_quantity', '<', 0)
                    ->orWhereColumn('reserved_quantity', '>', 'quantity'))
                ->count()),
            'open_closing_blockers' => $this->countWhen('accounting_closing_exceptions', fn () => DB::table('accounting_closing_exceptions')
                ->whereIn('status', ['open', 'pending'])->count()),
        ];

        $this->table(
            ['Check', 'Count'],
            collect($checks)->map(fn (int $count, string $name) => [$name, $count])->values()->all()
        );

        $blocking = collect($checks)->except('open_closing_blockers')->sum();
        if ($blocking > 0) {
            $this->error('Integrity findings require review. No data was changed.');

            return self::FAILURE;
        }

        $this->info('Read-only integrity check completed. No blocking inconsistency was found.');

        return self::SUCCESS;
    }

    private function countWhen(string $table, callable $callback): int
    {
        return Schema::hasTable($table) ? (int) $callback() : 0;
    }
}
