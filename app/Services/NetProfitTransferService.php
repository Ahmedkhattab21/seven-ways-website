<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Events\NetProfitTransferred;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class NetProfitTransferService
{
    public function __construct(private ClosingJournalService $journals)
    {
    }

    public function transfer(AccountingClosingRun $run, AccountingPeriod $period, int $summaryId, int $retainedId): JournalEntry
    {
        $row = DB::table('journal_entry_lines as jel')->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.company_id', $run->company_id)->where('je.fiscal_year_id', $run->fiscal_year_id)
            ->where('je.status', 'posted')->where('jel.account_id', $summaryId)
            ->selectRaw('COALESCE(SUM(jel.base_credit_amount - jel.base_debit_amount),0) profit')->first();
        $profit = bcadd((string) $row->profit, '0', 4);
        if (bccomp($profit, '0', 4) === 0) {
            throw new BusinessRuleException('Income summary has no profit or loss to transfer.');
        }
        $lines = bccomp($profit, '0', 4) === 1
            ? [['account_id' => $summaryId, 'debit' => $profit], ['account_id' => $retainedId, 'credit' => $profit]]
            : [['account_id' => $retainedId, 'debit' => bcmul($profit, '-1', 4)], ['account_id' => $summaryId, 'credit' => bcmul($profit, '-1', 4)]];
        $entry = $this->journals->create(
            $run,
            $period,
            'retained_earnings_transfer',
            $run->fiscalYear->end_date->toDateString(),
            $lines
        );
        DB::afterCommit(fn () => event(new NetProfitTransferred($entry->id)));

        return $entry;
    }
}
