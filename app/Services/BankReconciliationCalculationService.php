<?php

namespace App\Services;

use App\Models\BankReconciliationSession;

class BankReconciliationCalculationService
{
    public function __construct(private BankBookTransactionService $book)
    {
    }

    public function calculate(BankReconciliationSession $session): array
    {
        $session->loadMissing(['bankAccount', 'imports']);
        $imports = $session->imports->sortBy('period_start');
        $statementLines = \App\Models\BankStatementLine::query()
            ->whereIn('bank_statement_import_id', $imports->pluck('id'))
            ->whereNotIn('status', ['duplicate', 'ignored'])->get();
        $bookLines = $this->book->transactions(
            $session->bankAccount, $session->date_from->toDateString(), $session->date_to->toDateString()
        );
        $statementOpening = (string) ($imports->first()?->opening_balance ?? '0');
        $statementClosing = (string) ($imports->last()?->closing_balance ?? '0');
        $bookOpening = $this->book->openingBalance($session->bankAccount, $session->date_from->toDateString());
        $bookClosing = $this->book->closingBalance($session->bankAccount, $session->date_to->toDateString());
        $matchedStatement = $statementLines->reduce(
            fn ($sum, $line) => bcadd($sum, (string) $line->matched_amount, 4), '0.0000'
        );
        $matchedBook = $bookLines->reduce(
            fn ($sum, $line) => bcadd($sum, (string) $line->reconciliation_matched_amount, 4), '0.0000'
        );
        $unreconciledStatement = $statementLines->reduce(
            fn ($sum, $line) => bcadd($sum, (string) $line->unmatched_amount, 4), '0.0000'
        );
        $unreconciledBook = $bookLines->reduce(
            fn ($sum, $line) => bcadd($sum, (string) $line->reconciliation_unmatched_amount, 4), '0.0000'
        );
        $statementDebits = $statementLines->where('status', '!=', 'matched')->reduce(
            fn ($sum, $line) => bcadd($sum, $line->direction() === 'debit' ? (string) $line->unmatched_amount : '0', 4), '0.0000'
        );
        $statementCredits = $statementLines->where('status', '!=', 'matched')->reduce(
            fn ($sum, $line) => bcadd($sum, $line->direction() === 'credit' ? (string) $line->unmatched_amount : '0', 4), '0.0000'
        );
        $bookDebits = $bookLines->reduce(
            fn ($sum, $line) => bcadd($sum, $line->reconciliation_direction === 'debit' ? (string) $line->reconciliation_unmatched_amount : '0', 4), '0.0000'
        );
        $bookCredits = $bookLines->reduce(
            fn ($sum, $line) => bcadd($sum, $line->reconciliation_direction === 'credit' ? (string) $line->reconciliation_unmatched_amount : '0', 4), '0.0000'
        );

        return [
            'statement_opening_balance' => $statementOpening,
            'statement_closing_balance' => $statementClosing,
            'book_opening_balance' => $bookOpening,
            'book_closing_balance' => $bookClosing,
            'matched_statement_amount' => $matchedStatement,
            'matched_book_amount' => $matchedBook,
            'unmatched_statement_debits' => $statementDebits,
            'unmatched_statement_credits' => $statementCredits,
            'unmatched_book_debits' => $bookDebits,
            'unmatched_book_credits' => $bookCredits,
            'unreconciled_statement_amount' => $unreconciledStatement,
            'unreconciled_book_amount' => $unreconciledBook,
            'difference' => bcsub($statementClosing, $bookClosing, 4),
        ];
    }

    public function snapshot(BankReconciliationSession $session): BankReconciliationSession
    {
        $totals = $this->calculate($session);
        $session->forceFill(array_intersect_key($totals, array_flip([
            'statement_opening_balance', 'statement_closing_balance', 'book_opening_balance',
            'book_closing_balance', 'matched_statement_amount', 'matched_book_amount',
            'unreconciled_statement_amount', 'unreconciled_book_amount', 'difference',
        ])))->save();

        return $session;
    }
}
