<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankAdjustmentApproved;
use App\Events\BankAdjustmentCreated;
use App\Events\BankAdjustmentPosted;
use App\Events\BankAdjustmentReversed;
use App\Events\BankAdjustmentSubmitted;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankAdjustment;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\DB;

class BankAdjustmentService
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $access,
        private AccountingPeriodResolver $periods,
        private DocumentNumberService $numbers,
        private BankAdjustmentPostingService $posting,
        private AuditService $audit
    ) {
    }

    public function create(array $data): BankAdjustment
    {
        [$account, $offset, $branchId] = $this->scope($data);
        $this->periods->resolve(
            $account->company_id, $data['adjustment_date'], 'treasury',
            $this->tenant->user(), $data['override_reason'] ?? null
        );
        if ($offset->id === $account->gl_account_id || bccomp((string) $data['amount'], '0', 4) !== 1) {
            throw new BusinessRuleException('Adjustment amount must be positive and offset account must differ from bank GL.');
        }
        $this->assertStatementCapacity($data, $account);

        return DB::transaction(function () use ($data, $account, $branchId) {
            $adjustment = new BankAdjustment($data);
            $adjustment->forceFill([
                'company_id' => $account->company_id, 'bank_account_id' => $account->id,
                'branch_id' => $branchId,
                'document_number' => $this->numbers->next(
                    'bank_adjustment', $account->company_id, $branchId, $data['adjustment_date']
                ),
                'currency_id' => $account->currency_id, 'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('bank_adjustment.created', $adjustment);
            DB::afterCommit(fn () => event(new BankAdjustmentCreated($adjustment->id)));

            return $adjustment;
        });
    }

    public function update(BankAdjustment $adjustment, array $data): BankAdjustment
    {
        if ($adjustment->company_id !== $this->tenant->companyId() || $adjustment->status !== 'draft') {
            throw new BusinessRuleException('Only tenant draft bank adjustment can be edited.');
        }
        [$account, $offset, $branchId] = $this->scope($data);
        if ($account->id !== $adjustment->bank_account_id || $offset->id === $account->gl_account_id) {
            throw new BusinessRuleException('Adjustment scope cannot be changed to an invalid account.');
        }
        $this->assertStatementCapacity($data, $account, $adjustment->id);
        $adjustment->fill($data);
        $adjustment->forceFill(['branch_id' => $branchId, 'currency_id' => $account->currency_id])->save();
        $this->audit->record('bank_adjustment.updated', $adjustment);

        return $adjustment;
    }

    public function action(BankAdjustment $adjustment, string $action, array $data = []): BankAdjustment
    {
        return DB::transaction(function () use ($adjustment, $action, $data) {
            $adjustment = BankAdjustment::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($action === 'post') {
                return $this->post($adjustment, $data['override_reason'] ?? null);
            }
            if ($action === 'reverse') {
                return $this->reverse($adjustment, (string) ($data['reason'] ?? ''), $data['date'] ?? null);
            }
            $transitions = [
                'submit' => ['draft', 'pending_approval', 'submitted_by', 'submitted_at', BankAdjustmentSubmitted::class],
                'approve' => ['pending_approval', 'approved', 'approved_by', 'approved_at', BankAdjustmentApproved::class],
                'cancel' => [['draft', 'pending_approval', 'approved'], 'cancelled', null, null, null],
            ];
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported bank adjustment action.');
            }
            [$from, $to, $actor, $time, $event] = $transitions[$action];
            if (! in_array($adjustment->status, (array) $from, true)) {
                throw new BusinessRuleException('Invalid bank adjustment status transition.');
            }
            if ($action === 'approve' && $adjustment->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Adjustment creator cannot approve the same adjustment.');
            }
            $changes = ['status' => $to];
            if ($actor) {
                $changes += [$actor => $this->tenant->user()->id, $time => now()];
            }
            $adjustment->forceFill($changes)->save();
            $this->audit->record('bank_adjustment.'.$action, $adjustment);
            if ($event) {
                DB::afterCommit(fn () => event(new $event($adjustment->id)));
            }

            return $adjustment;
        });
    }

    private function post(BankAdjustment $adjustment, ?string $overrideReason): BankAdjustment
    {
        if ($adjustment->status === 'posted') {
            return $adjustment;
        }
        if ($adjustment->status !== 'approved' || $adjustment->approved_by === $this->tenant->user()->id) {
            throw new BusinessRuleException('Approved adjustment needs a different posting actor.');
        }
        $journal = $this->posting->post($adjustment, $overrideReason);
        $adjustment->forceFill([
            'status' => 'posted', 'journal_entry_id' => $journal->id,
            'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
        ])->save();
        $this->audit->record('bank_adjustment.posted', $adjustment, ['journal_entry_id' => $journal->id]);
        DB::afterCommit(fn () => event(new BankAdjustmentPosted($adjustment->id)));

        return $adjustment;
    }

    private function reverse(BankAdjustment $adjustment, string $reason, ?string $date): BankAdjustment
    {
        if ($adjustment->status === 'reversed') {
            return $adjustment;
        }
        if ($adjustment->status !== 'posted' || blank($reason)) {
            throw new BusinessRuleException('Only a posted adjustment can be reversed with a reason.');
        }
        $reversal = $this->posting->reverse($adjustment, $reason, $date);
        $adjustment->forceFill([
            'status' => 'reversed', 'reversal_journal_entry_id' => $reversal->id,
            'reversed_by' => $this->tenant->user()->id, 'reversed_at' => now(),
        ])->save();
        $this->audit->record('bank_adjustment.reversed', $adjustment, [
            'reversal_journal_entry_id' => $reversal->id, 'reason' => $reason,
        ]);
        DB::afterCommit(fn () => event(new BankAdjustmentReversed($adjustment->id)));

        return $adjustment;
    }

    private function scope(array $data): array
    {
        $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
            ->where('status', 'active')->findOrFail($data['bank_account_id']);
        $branchId = $account->branch_id ?: $this->tenant->branchId();
        $ability = in_array($data['adjustment_type'], ['bank_fee', 'interest_expense', 'unidentified_payment'], true)
            ? 'can_pay' : 'can_receive';
        $this->access->assert($account, (int) $branchId, $ability, (string) $data['amount']);
        $offset = Account::query()->where('company_id', $account->company_id)
            ->where('is_active', true)->where('is_posting', true)->findOrFail($data['offset_account_id']);

        return [$account, $offset, $branchId];
    }

    private function assertStatementCapacity(array $data, BankAccount $account, ?int $except = null): void
    {
        if (empty($data['bank_statement_line_id'])) {
            return;
        }
        $line = BankStatementLine::query()->where('company_id', $account->company_id)
            ->where('bank_account_id', $account->id)->findOrFail($data['bank_statement_line_id']);
        $allocated = BankAdjustment::query()->where('bank_statement_line_id', $line->id)
            ->whereNotIn('status', ['cancelled', 'reversed'])->when($except, fn ($query) => $query->whereKeyNot($except))
            ->sum('amount');
        if (bccomp(bcadd((string) $allocated, (string) $data['amount'], 4), $line->amount(), 4) === 1) {
            throw new BusinessRuleException('Statement line adjustment exceeds its amount.');
        }
    }
}
