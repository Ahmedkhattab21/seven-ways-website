<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliationMatchItem;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

class BankBookTransactionService
{
    public function openingBalance(BankAccount $account, string $date): string
    {
        $row = JournalEntryLine::query()->selectRaw(
            'COALESCE(SUM(journal_entry_lines.debit_amount),0) debit, COALESCE(SUM(journal_entry_lines.credit_amount),0) credit'
        )->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', 'posted')->where('journal_entry_lines.account_id', $account->gl_account_id)
            ->whereDate('journal_entries.posting_date', '<', $date)->first();

        return bcsub((string) $row->debit, (string) $row->credit, 4);
    }

    public function closingBalance(BankAccount $account, string $date): string
    {
        $row = JournalEntryLine::query()->selectRaw(
            'COALESCE(SUM(journal_entry_lines.debit_amount),0) debit, COALESCE(SUM(journal_entry_lines.credit_amount),0) credit'
        )->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', 'posted')->where('journal_entry_lines.account_id', $account->gl_account_id)
            ->whereDate('journal_entries.posting_date', '<=', $date)->first();

        return bcsub((string) $row->debit, (string) $row->credit, 4);
    }

    public function transactions(BankAccount $account, string $from, string $to): Collection
    {
        $lines = JournalEntryLine::query()->select('journal_entry_lines.*')
            ->with('entry:id,journal_number,source_type,source_number,posting_date,status')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', 'posted')->where('journal_entry_lines.account_id', $account->gl_account_id)
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->orderBy('journal_entries.posting_date')->orderBy('journal_entry_lines.id')->get();
        $allocated = BankReconciliationMatchItem::query()
            ->selectRaw('journal_entry_line_id, SUM(allocated_amount) allocated')
            ->join('bank_reconciliation_matches', 'bank_reconciliation_matches.id', '=', 'bank_reconciliation_match_items.bank_reconciliation_match_id')
            ->whereIn('bank_reconciliation_matches.status', ['accepted', 'completed'])
            ->whereNotNull('journal_entry_line_id')->groupBy('journal_entry_line_id')
            ->pluck('allocated', 'journal_entry_line_id');

        return $lines->map(function (JournalEntryLine $line) use ($allocated) {
            $amount = bccomp((string) $line->debit_amount, '0', 4) === 1
                ? (string) $line->debit_amount : (string) $line->credit_amount;
            $matched = (string) ($allocated[$line->id] ?? '0');
            $line->setAttribute('reconciliation_matched_amount', bcadd($matched, '0', 4));
            $line->setAttribute('reconciliation_unmatched_amount', bcsub($amount, $matched, 4));
            $line->setAttribute(
                'reconciliation_direction',
                bccomp((string) $line->debit_amount, '0', 4) === 1 ? 'debit' : 'credit'
            );

            return $line;
        });
    }
}
