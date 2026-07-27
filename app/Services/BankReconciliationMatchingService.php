<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankReconciliationMatchAccepted;
use App\Events\BankReconciliationMatchRejected;
use App\Events\BankReconciliationMatchReversed;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationMatchItem;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementLine;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class BankReconciliationMatchingService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function createManualMatch(
        BankReconciliationSession $session,
        array $statementAllocations,
        array $bookAllocations,
        string $method = 'manual',
        ?int $confidence = null,
        bool $suggested = false
    ): BankReconciliationMatch {
        return DB::transaction(function () use (
            $session, $statementAllocations, $bookAllocations, $method, $confidence, $suggested
        ) {
            $session = $this->lockEditableSession($session);
            [$statementItems, $statementTotal, $statementDirection] = $this->statementItems($session, $statementAllocations, ! $suggested);
            [$bookItems, $bookTotal, $bookDirection] = $this->bookItems($session, $bookAllocations, ! $suggested);
            if (($statementDirection === 'credit' ? 'debit' : 'credit') !== $bookDirection) {
                throw new BusinessRuleException('Statement and book match directions are incompatible.');
            }
            $difference = bcsub($statementTotal, $bookTotal, 4);
            if (bccomp($this->absolute($difference), (string) $session->tolerance, 4) === 1) {
                throw new BusinessRuleException('Match sides differ by more than reconciliation tolerance.');
            }
            $type = $this->matchType(count($statementItems), count($bookItems));
            $match = new BankReconciliationMatch([
                'bank_reconciliation_session_id' => $session->id, 'match_type' => $type,
                'match_method' => $method, 'confidence_score' => $confidence,
            ]);
            $match->forceFill([
                'company_id' => $session->company_id, 'status' => $suggested ? 'suggested' : 'accepted',
                'matched_amount' => bccomp($statementTotal, $bookTotal, 4) === -1 ? $statementTotal : $bookTotal,
                'difference_amount' => $difference, 'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ([...$statementItems, ...$bookItems] as $item) {
                $match->items()->create($item);
            }
            if (! $suggested) {
                $this->refreshStatementLines($match);
                $this->audit->record('bank_reconciliation.match_created', $match, [
                    'method' => $method, 'match_type' => $type,
                ]);
                DB::afterCommit(fn () => event(new BankReconciliationMatchAccepted($match->id)));
            }

            return $match->load('items');
        });
    }

    public function acceptSuggestedMatch(BankReconciliationMatch $match): BankReconciliationMatch
    {
        return DB::transaction(function () use ($match) {
            $match = BankReconciliationMatch::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($match->status !== 'suggested') {
                throw new BusinessRuleException('Only a suggested match can be accepted.');
            }
            $session = $this->lockEditableSession($match->session);
            $statement = $match->items->where('side', 'statement')->map(fn ($item) => [
                'id' => $item->statement_line_id, 'amount' => $item->allocated_amount,
            ])->all();
            $book = $match->items->where('side', 'book')->map(fn ($item) => [
                'id' => $item->journal_entry_line_id, 'amount' => $item->allocated_amount,
            ])->all();
            $this->statementItems($session, $statement, true);
            $this->bookItems($session, $book, true);
            $match->forceFill(['status' => 'accepted', 'reviewed_by' => $this->tenant->user()->id])->save();
            $this->refreshStatementLines($match);
            $this->audit->record('bank_reconciliation.match_accepted', $match);
            DB::afterCommit(fn () => event(new BankReconciliationMatchAccepted($match->id)));

            return $match;
        });
    }

    public function rejectSuggestedMatch(BankReconciliationMatch $match): BankReconciliationMatch
    {
        return DB::transaction(function () use ($match) {
            $match = BankReconciliationMatch::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->lockEditableSession($match->session);
            if ($match->status !== 'suggested') {
                throw new BusinessRuleException('Only a suggested match can be rejected.');
            }
            $match->forceFill(['status' => 'rejected', 'reviewed_by' => $this->tenant->user()->id])->save();
            $this->audit->record('bank_reconciliation.match_rejected', $match);
            DB::afterCommit(fn () => event(new BankReconciliationMatchRejected($match->id)));

            return $match;
        });
    }

    public function unmatch(BankReconciliationMatch $match, string $reason): BankReconciliationMatch
    {
        return DB::transaction(function () use ($match, $reason) {
            $match = BankReconciliationMatch::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->lockEditableSession($match->session);
            if ($match->status !== 'accepted' || blank($reason)) {
                throw new BusinessRuleException('Only accepted match can be reversed with a reason.');
            }
            $match->forceFill(['status' => 'reversed', 'reviewed_by' => $this->tenant->user()->id])->save();
            $this->refreshStatementLines($match);
            $this->audit->record('bank_reconciliation.match_reversed', $match, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new BankReconciliationMatchReversed($match->id)));

            return $match;
        });
    }

    private function statementItems(BankReconciliationSession $session, array $allocations, bool $enforceAvailable): array
    {
        if ($allocations === []) {
            throw new BusinessRuleException('At least one statement allocation is required.');
        }
        $importIds = DB::table('bank_reconciliation_session_imports')
            ->where('bank_reconciliation_session_id', $session->id)->pluck('bank_statement_import_id');
        $items = [];
        $total = '0.0000';
        $directions = [];
        foreach ($allocations as $allocation) {
            $amount = $this->positive($allocation['amount'] ?? null);
            $line = BankStatementLine::query()->whereIn('bank_statement_import_id', $importIds)
                ->where('company_id', $session->company_id)->where('bank_account_id', $session->bank_account_id)
                ->whereKey($allocation['id'] ?? 0)->lockForUpdate()->firstOrFail();
            if (in_array($line->status, ['ignored', 'duplicate'], true) || $line->currency_id !== $session->bankAccount->currency_id) {
                throw new BusinessRuleException('Statement line is not eligible for matching.');
            }
            if ($enforceAvailable && bccomp($amount, (string) $line->unmatched_amount, 4) === 1) {
                throw new BusinessRuleException('Statement line allocation exceeds remaining amount.');
            }
            $items[] = [
                'side' => 'statement', 'statement_line_id' => $line->id,
                'journal_entry_line_id' => null, 'bank_adjustment_id' => null, 'allocated_amount' => $amount,
            ];
            $directions[$line->direction()] = true;
            $total = bcadd($total, $amount, 4);
        }
        if (count($directions) !== 1) {
            throw new BusinessRuleException('A match cannot mix statement debit and credit directions.');
        }

        return [$items, $total, array_key_first($directions)];
    }

    private function bookItems(BankReconciliationSession $session, array $allocations, bool $enforceAvailable): array
    {
        if ($allocations === []) {
            throw new BusinessRuleException('At least one book allocation is required.');
        }
        $items = [];
        $total = '0.0000';
        $directions = [];
        foreach ($allocations as $allocation) {
            $amount = $this->positive($allocation['amount'] ?? null);
            $line = JournalEntryLine::query()->select('journal_entry_lines.*')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.company_id', $session->company_id)->where('journal_entries.status', 'posted')
                ->where('journal_entry_lines.account_id', $session->bankAccount->gl_account_id)
                ->whereBetween('journal_entries.posting_date', [$session->date_from, $session->date_to])
                ->where('journal_entry_lines.id', $allocation['id'] ?? 0)->lockForUpdate()->firstOrFail();
            $lineAmount = bccomp((string) $line->debit_amount, '0', 4) === 1
                ? (string) $line->debit_amount : (string) $line->credit_amount;
            $allocated = $this->allocatedBook($line->id);
            if ($enforceAvailable && bccomp($amount, bcsub($lineAmount, $allocated, 4), 4) === 1) {
                throw new BusinessRuleException('Book line allocation exceeds remaining amount.');
            }
            $items[] = [
                'side' => 'book', 'statement_line_id' => null,
                'journal_entry_line_id' => $line->id, 'bank_adjustment_id' => null, 'allocated_amount' => $amount,
            ];
            $directions[bccomp((string) $line->debit_amount, '0', 4) === 1 ? 'debit' : 'credit'] = true;
            $total = bcadd($total, $amount, 4);
        }
        if (count($directions) !== 1) {
            throw new BusinessRuleException('A match cannot mix book debit and credit directions.');
        }

        return [$items, $total, array_key_first($directions)];
    }

    private function refreshStatementLines(BankReconciliationMatch $match): void
    {
        $ids = $match->items()->whereNotNull('statement_line_id')->pluck('statement_line_id');
        foreach (BankStatementLine::query()->whereIn('id', $ids)->lockForUpdate()->get() as $line) {
            $matched = BankReconciliationMatchItem::query()
                ->join('bank_reconciliation_matches', 'bank_reconciliation_matches.id', '=', 'bank_reconciliation_match_items.bank_reconciliation_match_id')
                ->where('statement_line_id', $line->id)
                ->whereIn('bank_reconciliation_matches.status', ['accepted', 'completed'])->sum('allocated_amount');
            $remaining = bcsub($line->amount(), (string) $matched, 4);
            $line->forceFill([
                'matched_amount' => bcadd((string) $matched, '0', 4), 'unmatched_amount' => $remaining,
                'status' => bccomp($remaining, '0', 4) === 0 ? 'matched' : (bccomp((string) $matched, '0', 4) === 1 ? 'partially_matched' : 'unmatched'),
            ])->save();
        }
    }

    private function allocatedBook(int $lineId): string
    {
        return bcadd((string) BankReconciliationMatchItem::query()
            ->join('bank_reconciliation_matches', 'bank_reconciliation_matches.id', '=', 'bank_reconciliation_match_items.bank_reconciliation_match_id')
            ->where('journal_entry_line_id', $lineId)
            ->whereIn('bank_reconciliation_matches.status', ['accepted', 'completed'])->sum('allocated_amount'), '0', 4);
    }

    private function lockEditableSession(BankReconciliationSession $session): BankReconciliationSession
    {
        $session = BankReconciliationSession::query()->where('company_id', $this->tenant->companyId())
            ->whereKey($session->id)->lockForUpdate()->firstOrFail();
        if (! in_array($session->status, ['matching', 'ready_for_review', 'reopened'], true)) {
            throw new BusinessRuleException('Reconciliation session is not editable.');
        }

        return $session;
    }

    private function positive(mixed $value): string
    {
        $value = bcadd((string) $value, '0', 4);
        if (bccomp($value, '0', 4) !== 1) {
            throw new BusinessRuleException('Allocated amount must be positive.');
        }

        return $value;
    }

    private function matchType(int $statement, int $book): string
    {
        return match (true) {
            $statement === 1 && $book === 1 => 'one_to_one',
            $statement === 1 => 'one_to_many',
            $book === 1 => 'many_to_one',
            default => 'many_to_many',
        };
    }

    private function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
