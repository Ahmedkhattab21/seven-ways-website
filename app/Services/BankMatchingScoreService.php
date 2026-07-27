<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\JournalEntryLine;

class BankMatchingScoreService
{
    public function score(BankStatementLine $statement, JournalEntryLine $book): array
    {
        $score = 0;
        $reasons = [];
        $metadata = $book->metadata ?? [];
        if ($statement->external_id && $statement->external_id === ($metadata['external_id'] ?? null)) {
            $score += 100;
            $reasons[] = 'exact_external_id';
        }
        if ($statement->bank_reference
            && in_array($statement->bank_reference, [$book->reference, $book->entry?->source_number], true)) {
            $score += 90;
            $reasons[] = 'exact_reference';
        }
        $bookAmount = bccomp((string) $book->debit_amount, '0', 4) === 1 ? $book->debit_amount : $book->credit_amount;
        if (bccomp($statement->amount(), (string) $bookAmount, 4) === 0) {
            $score += 50;
            $reasons[] = 'exact_amount';
        }
        $days = abs($statement->transaction_date->diffInDays($book->entry->posting_date, false));
        if ($days === 0) {
            $score += 20;
            $reasons[] = 'same_day';
        } elseif ($days <= 3) {
            $score += 10;
            $reasons[] = 'within_three_days';
        }
        if ($statement->counterparty_iban_last4
            && $statement->counterparty_iban_last4 === ($metadata['counterparty_iban_last4'] ?? null)) {
            $score += 15;
            $reasons[] = 'iban_last4';
        }

        return ['score' => min(100, $score), 'reasons' => $reasons];
    }
}
