<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ChequeStatusChanged;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\ChequeEndorsement;
use Illuminate\Support\Facades\DB;

class ChequeService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private TreasuryOperationAuthorizationService $authorization,
        private ChequePostingService $posting,
        private AuditService $audit
    ) {
    }

    public function create(array $data): Cheque
    {
        return DB::transaction(function () use ($data) {
            $this->authorization->assert(
                'treasury.cheques.create', $data['direction'].'_cheque', 'create',
                (int) $data['currency_id'], (string) $data['amount'], (int) $data['branch_id']
            );
            Bank::query()->where(fn ($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $this->tenant->companyId()))->where('is_active', true)
                ->findOrFail($data['bank_id']);
            $account = null;
            if (! empty($data['bank_account_id'])) {
                $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
                    ->where('status', 'active')->findOrFail($data['bank_account_id']);
                if ($account->bank_id !== (int) $data['bank_id']
                    || $account->currency_id !== (int) $data['currency_id']) {
                    throw new BusinessRuleException('Cheque bank account mapping is invalid.');
                }
            }
            foreach (['clearing_account_id', 'offset_account_id'] as $field) {
                if (! empty($data[$field])) {
                    $gl = Account::query()->where('company_id', $this->tenant->companyId())
                        ->where('is_active', true)->where('is_posting', true)->findOrFail($data[$field]);
                    if ($field === 'offset_account_id' && $gl->is_control_account
                        && ! $this->tenant->user()->hasPermission('accounting.journals.post_control_accounts')) {
                        throw new BusinessRuleException('Cheque control account requires explicit permission.');
                    }
                }
            }
            $scopeKey = implode(':', [
                $this->tenant->companyId(), $data['bank_id'], $data['direction'],
                $data['bank_account_id'] ?? 0, hash('sha256', mb_strtoupper(trim($data['cheque_number']))),
            ]);
            if (Cheque::query()->where('cheque_scope_key', $scopeKey)->exists()) {
                throw new BusinessRuleException('Cheque number already exists in the same bank scope.');
            }
            $cheque = new Cheque($data);
            $cheque->forceFill([
                'company_id' => $this->tenant->companyId(), 'cheque_scope_key' => $scopeKey,
                'document_number' => $this->numbers->next(
                    'cheque_'.$data['direction'], $this->tenant->companyId(),
                    $data['branch_id'], $data['issue_date']
                ),
                'bank_gl_account_id' => $account?->gl_account_id, 'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->history($cheque, null, 'draft');
            $this->audit->record('treasury.cheque.created', $cheque, [
                'direction' => $cheque->direction, 'number' => $cheque->maskedNumber(),
            ]);

            return $cheque;
        });
    }

    public function action(Cheque $cheque, string $action, array $data = []): Cheque
    {
        return DB::transaction(function () use ($cheque, $action, $data) {
            $cheque = Cheque::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($cheque->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($cheque->branch)) {
                throw new BusinessRuleException('Cheque branch is outside the actor scope.');
            }
            $from = $cheque->status;
            $changes = [];
            $operation = match ($action) {
                'clear' => 'cheque_clearance',
                'bounce' => 'cheque_bounce',
                default => $cheque->direction.'_cheque',
            };
            $ability = match ($action) {
                'submit' => 'submit',
                'approve' => 'approve',
                'clear', 'bounce' => 'post',
                default => null,
            };
            if ($ability) {
                $this->authorization->assert(
                    'treasury.cheques.'.$action, $operation, $ability, $cheque->currency_id,
                    (string) $cheque->amount, $cheque->branch_id, $cheque->created_by
                );
            }
            if ($action === 'submit') {
                if ($from !== 'draft') {
                    throw new BusinessRuleException('Only draft cheques can be submitted.');
                }
                $changes = [
                    'status' => $cheque->direction === 'received' ? 'received' : 'issued',
                    'submitted_by' => $this->tenant->user()->id,
                ];
            } elseif ($action === 'approve') {
                $expected = $cheque->direction === 'received' ? 'received' : 'issued';
                if ($from !== $expected || $cheque->approved_by) {
                    throw new BusinessRuleException('Cheque is not eligible for approval.');
                }
                if ($cheque->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Cheque creator cannot approve the same cheque.');
                }
                $entry = $this->posting->recognize($cheque);
                $changes = [
                    'status' => $cheque->direction === 'received' ? 'on_hand' : 'issued',
                    'approved_by' => $this->tenant->user()->id, 'journal_entry_id' => $entry->id,
                ];
            } elseif ($action === 'deposit') {
                $this->assertTransition($cheque, 'received', ['on_hand']);
                $changes = [
                    'status' => 'deposited', 'deposit_date' => $data['date'],
                    'deposited_by' => $this->tenant->user()->id,
                ];
            } elseif ($action === 'present') {
                $this->assertTransition($cheque, 'issued', ['issued']);
                if (! $cheque->approved_by) {
                    throw new BusinessRuleException('Issued cheque must be approved before presentation.');
                }
                $changes = ['status' => 'presented'];
            } elseif ($action === 'clear') {
                $allowed = $cheque->direction === 'received'
                    ? ['deposited', 'under_collection'] : ['presented'];
                if (! in_array($from, $allowed, true) || $cheque->clearance_journal_entry_id) {
                    throw new BusinessRuleException('Cheque cannot be cleared from its current state.');
                }
                $entry = $this->posting->clear($cheque, $data['date']);
                $changes = [
                    'status' => 'cleared', 'clearance_date' => $data['date'],
                    'cleared_by' => $this->tenant->user()->id,
                    'clearance_journal_entry_id' => $entry->id,
                ];
            } elseif ($action === 'bounce') {
                if ($from !== 'cleared' || $cheque->bounce_journal_entry_id || blank($data['reason'] ?? null)) {
                    throw new BusinessRuleException('Cleared cheque and bounce reason are required.');
                }
                $entry = $this->posting->bounce($cheque, $data['reason'], $data['date']);
                $changes = [
                    'status' => 'bounced', 'bounce_date' => $data['date'],
                    'bounced_by' => $this->tenant->user()->id, 'bounce_journal_entry_id' => $entry->id,
                ];
            } elseif (in_array($action, ['return', 'cancel'], true)) {
                $allowed = $action === 'return'
                    ? ($cheque->direction === 'received'
                        ? ['on_hand', 'deposited', 'under_collection', 'bounced']
                        : ['issued', 'presented', 'bounced'])
                    : ['draft', 'received', 'issued'];
                if (! in_array($from, $allowed, true) || blank($data['reason'] ?? null)) {
                    throw new BusinessRuleException('Cheque return/cancellation is not allowed.');
                }
                $changes = ['status' => $action === 'return' ? 'returned' : 'cancelled'];
                if ($action === 'cancel') {
                    $changes['cancelled_by'] = $this->tenant->user()->id;
                }
                if ($cheque->journal_entry_id) {
                    $entry = $this->posting->reverseRecognition($cheque, $data['reason'], $data['date'] ?? null);
                    $changes['reversal_journal_entry_id'] = $entry->id;
                }
            } else {
                throw new BusinessRuleException('Unsupported cheque action.');
            }
            $cheque->forceFill($changes)->save();
            $this->history($cheque, $from, $cheque->status, $data['reason'] ?? null);
            $this->audit->record('treasury.cheque.'.$action, $cheque, [
                'number' => $cheque->maskedNumber(), 'from_status' => $from, 'to_status' => $cheque->status,
            ]);
            $specificEvent = match ($action) {
                'submit' => $cheque->direction === 'received'
                    ? \App\Events\ChequeReceived::class : \App\Events\ChequeIssued::class,
                'deposit' => \App\Events\ChequeDeposited::class,
                'present' => \App\Events\ChequePresented::class,
                'clear' => \App\Events\ChequeCleared::class,
                'bounce' => \App\Events\ChequeBounced::class,
                'return' => \App\Events\ChequeReturned::class,
                'cancel' => \App\Events\ChequeCancelled::class,
                default => null,
            };
            if ($specificEvent) {
                DB::afterCommit(fn () => event(new $specificEvent($cheque->id)));
            }
            DB::afterCommit(fn () => event(new ChequeStatusChanged($cheque->id, $cheque->status)));

            return $cheque;
        });
    }

    public function endorse(Cheque $cheque, array $data): ChequeEndorsement
    {
        return DB::transaction(function () use ($cheque, $data) {
            $cheque = Cheque::query()->where('company_id', $this->tenant->companyId())
                ->where('direction', 'received')->where('status', 'on_hand')
                ->whereKey($cheque->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($cheque->branch)) {
                throw new BusinessRuleException('Cheque branch is outside the actor scope.');
            }
            $endorsement = new ChequeEndorsement($data);
            $endorsement->forceFill([
                'company_id' => $cheque->company_id, 'cheque_id' => $cheque->id,
                'status' => 'pending_approval', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->history($cheque, $cheque->status, $cheque->status, 'Endorsement requested');
            $this->audit->record('treasury.cheque.endorsement_created', $cheque);
            DB::afterCommit(fn () => event(new \App\Events\ChequeEndorsed($cheque->id)));

            return $endorsement;
        });
    }

    public function replace(Cheque $cheque, array $data): Cheque
    {
        return DB::transaction(function () use ($cheque, $data) {
            $cheque = Cheque::query()->where('company_id', $this->tenant->companyId())
                ->whereIn('status', ['bounced', 'returned', 'cancelled'])
                ->whereKey($cheque->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($cheque->branch)) {
                throw new BusinessRuleException('Cheque branch is outside the actor scope.');
            }
            foreach (['replacement_cheque_number', 'replacement_issue_date', 'replacement_due_date'] as $field) {
                if (blank($data[$field] ?? null)) {
                    throw new BusinessRuleException('Replacement cheque number and dates are required.');
                }
            }
            $this->authorization->assert(
                'treasury.cheques.replace', $cheque->direction.'_cheque', 'create',
                $cheque->currency_id, (string) $cheque->amount, $cheque->branch_id
            );
            $scopeKey = implode(':', [
                $cheque->company_id, $cheque->bank_id, $cheque->direction, $cheque->bank_account_id ?: 0,
                hash('sha256', mb_strtoupper(trim($data['replacement_cheque_number']))),
            ]);
            if (Cheque::query()->where('cheque_scope_key', $scopeKey)->exists()) {
                throw new BusinessRuleException('Replacement cheque number already exists.');
            }
            $replacement = $cheque->replicate([
                'uuid', 'cheque_number', 'cheque_scope_key', 'document_number', 'status',
                'source_type', 'source_id', 'journal_entry_id', 'clearance_journal_entry_id',
                'bounce_journal_entry_id', 'reversal_journal_entry_id', 'created_by', 'submitted_by',
                'approved_by', 'deposited_by', 'cleared_by', 'bounced_by', 'cancelled_by',
                'deposit_date', 'clearance_date', 'bounce_date', 'created_at', 'updated_at',
            ]);
            $replacement->forceFill([
                'cheque_number' => $data['replacement_cheque_number'],
                'cheque_scope_key' => $scopeKey, 'issue_date' => $data['replacement_issue_date'],
                'due_date' => $data['replacement_due_date'], 'received_date' => null,
                'document_number' => $this->numbers->next(
                    'cheque_'.$cheque->direction, $cheque->company_id, $cheque->branch_id,
                    $data['replacement_issue_date']
                ),
                'status' => 'draft', 'source_type' => 'cheque_replacement', 'source_id' => $cheque->id,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $from = $cheque->status;
            $cheque->forceFill(['status' => 'replaced'])->save();
            $this->history($cheque, $from, 'replaced', 'Replacement cheque created');
            $this->history($replacement, null, 'draft', 'Replacement of '.$cheque->document_number);
            $this->audit->record('treasury.cheque.replaced', $cheque, [
                'replacement_id' => $replacement->id, 'number' => $replacement->maskedNumber(),
            ]);
            DB::afterCommit(fn () => event(new \App\Events\ChequeReplaced($cheque->id)));

            return $replacement;
        });
    }

    public function approveEndorsement(ChequeEndorsement $endorsement): ChequeEndorsement
    {
        return DB::transaction(function () use ($endorsement) {
            $endorsement = ChequeEndorsement::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'pending_approval')->whereKey($endorsement->id)->lockForUpdate()->firstOrFail();
            if ($endorsement->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Endorsement creator cannot approve it.');
            }
            $endorsement->forceFill([
                'status' => 'approved', 'approved_by' => $this->tenant->user()->id,
            ])->save();
            $this->history($endorsement->cheque, 'on_hand', 'on_hand', 'Endorsement approved; posting deferred');

            return $endorsement;
        });
    }

    private function assertTransition(Cheque $cheque, string $direction, array $statuses): void
    {
        if ($cheque->direction !== $direction || ! in_array($cheque->status, $statuses, true)) {
            throw new BusinessRuleException('Invalid cheque transition.');
        }
    }

    private function history(Cheque $cheque, ?string $from, string $to, ?string $reason = null): void
    {
        $history = $cheque->histories()->make([
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
        ]);
        $history->forceFill([
            'company_id' => $cheque->company_id,
            'changed_by' => $this->tenant->user()->id, 'changed_at' => now(),
        ])->save();
    }
}
