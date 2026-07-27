<?php

namespace App\Services;

use App\Events\OpeningCarryForwardCreated;
use App\Models\AccountingClosingRun;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class OpeningCarryForwardService
{
    public function __construct(private ClosingJournalService $journals)
    {
    }

    public function create(AccountingClosingRun $run, FiscalYear $next): JournalEntry
    {
        $period = $next->periods()->whereDate('start_date', '<=', $next->start_date)
            ->whereDate('end_date', '>=', $next->start_date)->firstOrFail();
        $lines = DB::table('journal_entry_lines as jel')->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('je.company_id', $run->company_id)->where('je.fiscal_year_id', $run->fiscal_year_id)
            ->where('je.status', 'posted')->whereIn('at.classification', ['asset', 'liability', 'equity'])
            ->groupBy('jel.account_id')->selectRaw('jel.account_id, SUM(jel.base_debit_amount - jel.base_credit_amount) balance')
            ->get()->filter(fn ($row) => bccomp((string) $row->balance, '0', 4) !== 0)
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'debit' => bccomp((string) $row->balance, '0', 4) === 1 ? $row->balance : '0.0000',
                'credit' => bccomp((string) $row->balance, '0', 4) === -1 ? bcmul((string) $row->balance, '-1', 4) : '0.0000',
            ])->values()->all();
        $entry = $this->journals->create(
            $run,
            $period,
            'opening_carry_forward',
            $next->start_date->toDateString(),
            $lines,
            true
        );
        DB::afterCommit(fn () => event(new OpeningCarryForwardCreated($entry->id)));

        return $entry;
    }
}
