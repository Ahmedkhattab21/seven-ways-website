<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankReconciliationMatchSuggested;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankMatchingSuggestionService
{
    public function __construct(
        private TenantContext $tenant,
        private BankBookTransactionService $book,
        private BankMatchingScoreService $scores,
        private BankReconciliationMatchingService $matching,
        private BankMatchingRuleService $rules
    ) {
    }

    public function suggest(BankReconciliationSession $session, int $threshold = 40, bool $autoMatch = false): Collection
    {
        if ($session->company_id !== $this->tenant->companyId()
            || ! in_array($session->status, ['matching', 'reopened'], true)) {
            throw new BusinessRuleException('Reconciliation session is not open for suggestions.');
        }
        if ($autoMatch && ! $this->tenant->user()->hasPermission('treasury.reconciliation.auto_match')) {
            throw new BusinessRuleException('Controlled auto-match permission is required.');
        }
        $imports = $session->imports()->pluck('bank_statement_imports.id');
        $statements = BankStatementLine::query()->whereIn('bank_statement_import_id', $imports)
            ->whereIn('status', ['unmatched', 'partially_matched'])->where('is_duplicate', false)
            ->orderBy('transaction_date')->limit(500)->get();
        $books = $this->book->transactions(
            $session->bankAccount, $session->date_from->toDateString(), $session->date_to->toDateString()
        )->filter(fn ($line) => bccomp((string) $line->reconciliation_unmatched_amount, '0', 4) === 1);
        $created = collect();
        foreach ($statements as $statement) {
            $rule = $this->rules->applicable($statement)->first();
            if ($rule?->result_type === 'ignore') {
                continue;
            }
            $candidate = $books->filter(function ($book) use ($statement) {
                $opposite = $statement->direction() === 'credit' ? 'debit' : 'credit';

                return $book->reconciliation_direction === $opposite
                    && abs($statement->transaction_date->diffInDays($book->entry->posting_date, false)) <= 7;
            })->map(fn ($book) => ['book' => $book, 'score' => $this->scores->score($statement, $book)])
                ->sortByDesc('score.score')->first();
            $minimum = $rule ? max($threshold, (int) $rule->minimum_confidence) : $threshold;
            if (! $candidate || $candidate['score']['score'] < $minimum) {
                continue;
            }
            $amount = bccomp((string) $statement->unmatched_amount, (string) $candidate['book']->reconciliation_unmatched_amount, 4) === -1
                ? (string) $statement->unmatched_amount : (string) $candidate['book']->reconciliation_unmatched_amount;
            $isAutomatic = $autoMatch && $rule?->auto_match && $candidate['score']['score'] >= $minimum;
            $match = $this->matching->createManualMatch(
                $session, [['id' => $statement->id, 'amount' => $amount]],
                [['id' => $candidate['book']->id, 'amount' => $amount]],
                $isAutomatic ? 'automatic' : 'rule', $candidate['score']['score'], ! $isAutomatic
            );
            $created->push($match);
            if (! $isAutomatic) {
                DB::afterCommit(fn () => event(new BankReconciliationMatchSuggested($match->id)));
            }
        }

        return $created;
    }
}
