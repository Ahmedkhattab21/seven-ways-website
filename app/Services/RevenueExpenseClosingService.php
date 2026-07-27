<?php

namespace App\Services;

use App\Events\ExpenseAccountsClosed;
use App\Events\RevenueAccountsClosed;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class RevenueExpenseClosingService
{
    public function __construct(private ClosingJournalService $journals)
    {
    }

    public function revenue(AccountingClosingRun $run, AccountingPeriod $period, int $targetAccountId): JournalEntry
    {
        $lines = $this->balances($run, 'revenue')->flatMap(function ($row) use ($targetAccountId) {
            $net = bcsub((string) $row->credit, (string) $row->debit, 4);
            if (bccomp($net, '0', 4) === 0) {
                return [];
            }

            return bccomp($net, '0', 4) === 1
                ? [['account_id' => $row->account_id, 'debit' => $net], ['account_id' => $targetAccountId, 'credit' => $net]]
                : [['account_id' => $row->account_id, 'credit' => bcmul($net, '-1', 4)], ['account_id' => $targetAccountId, 'debit' => bcmul($net, '-1', 4)]];
        })->all();
        $entry = $this->journals->create($run, $period, 'revenue_close', $run->fiscalYear->end_date->toDateString(), $this->combine($lines));
        DB::afterCommit(fn () => event(new RevenueAccountsClosed($entry->id)));

        return $entry;
    }

    public function expense(AccountingClosingRun $run, AccountingPeriod $period, int $targetAccountId): JournalEntry
    {
        $lines = $this->balances($run, 'expense')->flatMap(function ($row) use ($targetAccountId) {
            $net = bcsub((string) $row->debit, (string) $row->credit, 4);
            if (bccomp($net, '0', 4) === 0) {
                return [];
            }

            return bccomp($net, '0', 4) === 1
                ? [['account_id' => $targetAccountId, 'debit' => $net], ['account_id' => $row->account_id, 'credit' => $net]]
                : [['account_id' => $targetAccountId, 'credit' => bcmul($net, '-1', 4)], ['account_id' => $row->account_id, 'debit' => bcmul($net, '-1', 4)]];
        })->all();
        $entry = $this->journals->create($run, $period, 'expense_close', $run->fiscalYear->end_date->toDateString(), $this->combine($lines));
        DB::afterCommit(fn () => event(new ExpenseAccountsClosed($entry->id)));

        return $entry;
    }

    private function balances(AccountingClosingRun $run, string $classification)
    {
        return DB::table('journal_entry_lines as jel')->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('je.company_id', $run->company_id)->where('je.fiscal_year_id', $run->fiscal_year_id)
            ->where('je.status', 'posted')->where('at.classification', $classification)
            ->groupBy('jel.account_id')->selectRaw('jel.account_id, SUM(jel.base_debit_amount) debit, SUM(jel.base_credit_amount) credit')->get();
    }

    private function combine(array $lines): array
    {
        return collect($lines)->groupBy('account_id')->map(function ($items, $accountId) {
            $debit = $items->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['debit'] ?? 0), 4), '0.0000');
            $credit = $items->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['credit'] ?? 0), 4), '0.0000');
            $net = bcsub($debit, $credit, 4);

            return ['account_id' => $accountId, 'debit' => bccomp($net, '0', 4) === 1 ? $net : '0.0000',
                'credit' => bccomp($net, '0', 4) === -1 ? bcmul($net, '-1', 4) : '0.0000'];
        })->values()->all();
    }
}
