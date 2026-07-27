<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\TreasuryTransferCompleted;
use App\Events\TreasuryTransferFailed;
use App\Events\TreasuryTransferProcessingStarted;
use App\Events\TreasuryTransferReversed;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TreasuryTransferProcessingService
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $bankAccess,
        private CashBoxCustodianService $custodians,
        private TreasuryOperationAuthorizationService $authorization,
        private TreasuryTransferPostingService $posting,
        private AuditService $audit
    ) {
    }

    public function process(TreasuryTransfer $transfer): TreasuryTransfer
    {
        try {
            return DB::transaction(function () use ($transfer) {
                $transfer = TreasuryTransfer::query()->where('company_id', $this->tenant->companyId())
                    ->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($transfer->status === 'completed') {
                    return $transfer;
                }
                if (! in_array($transfer->status, ['approved', 'failed'], true)) {
                    throw new BusinessRuleException('Only approved or corrected failed transfers can be processed.');
                }
                $this->assertAccess($transfer);
                $this->authorization->assert(
                    'treasury.transfers.process', 'treasury_transfer', 'post',
                    $transfer->currency_id, (string) $transfer->amount, $transfer->branch_id
                );
                $transfer->forceFill([
                    'status' => 'processing', 'processed_by' => $this->tenant->user()->id,
                    'processed_at' => now(), 'failed_at' => null, 'failure_reason' => null,
                    'idempotency_key' => $transfer->idempotency_key ?: (string) Str::uuid(),
                ])->save();
                DB::afterCommit(fn () => event(new TreasuryTransferProcessingStarted($transfer->id)));
                $journal = $this->posting->post($transfer);
                $transfer->forceFill([
                    'status' => 'completed', 'journal_entry_id' => $journal->id,
                    'completed_by' => $this->tenant->user()->id, 'completed_at' => now(),
                ])->save();
                $this->audit->record('treasury.transfer.completed', $transfer, ['journal_entry_id' => $journal->id]);
                DB::afterCommit(fn () => event(new TreasuryTransferCompleted($transfer->id)));

                return $transfer;
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($transfer, $exception) {
                $locked = TreasuryTransfer::query()->where('company_id', $this->tenant->companyId())
                    ->whereKey($transfer->id)->lockForUpdate()->first();
                if ($locked && in_array($locked->status, ['approved', 'failed', 'processing'], true)) {
                    $locked->forceFill([
                        'status' => 'failed', 'failed_at' => now(),
                        'failure_reason' => Str::limit($exception->getMessage(), 1900),
                    ])->save();
                    $this->audit->record('treasury.transfer.failed', $locked);
                    DB::afterCommit(fn () => event(new TreasuryTransferFailed($locked->id)));
                }
            });
            throw $exception;
        }
    }

    public function reverse(TreasuryTransfer $transfer, string $reason, ?string $date = null): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $date) {
            $transfer = TreasuryTransfer::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'completed' || $transfer->reversal_journal_entry_id) {
                throw new BusinessRuleException('Only a completed unreversed transfer can be reversed.');
            }
            $reversal = $this->posting->reverse($transfer, $reason, $date);
            $transfer->forceFill([
                'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                'reversed_at' => now(), 'reversal_journal_entry_id' => $reversal->id,
            ])->save();
            $this->audit->record('treasury.transfer.reversed', $transfer, [
                'reversal_journal_entry_id' => $reversal->id,
            ]);
            DB::afterCommit(fn () => event(new TreasuryTransferReversed($transfer->id)));

            return $transfer;
        });
    }

    private function assertAccess(TreasuryTransfer $transfer): void
    {
        foreach (['from', 'to'] as $side) {
            $type = $transfer->{$side.'_type'};
            $branchId = $side === 'from'
                ? $transfer->branch_id : ($transfer->destination_branch_id ?: $transfer->branch_id);
            $branch = \App\Models\Branch::query()->where('company_id', $transfer->company_id)
                ->findOrFail($branchId);
            if (! $this->tenant->user()->canAccessBranch($branch)) {
                throw new BusinessRuleException('Transfer endpoint branch is outside the actor scope.');
            }
            if ($type === 'bank') {
                $bank = BankAccount::query()->where('company_id', $transfer->company_id)
                    ->where('status', 'active')->findOrFail($transfer->{$side.'_bank_account_id'});
                $this->bankAccess->assert($bank, $branchId, 'can_transfer', (string) $transfer->amount);
            } else {
                $cash = CashBox::query()->where('company_id', $transfer->company_id)
                    ->where('status', 'active')->findOrFail($transfer->{$side.'_cash_box_id'});
                $this->custodians->assert($cash, 'can_transfer', (string) $transfer->amount);
            }
        }
    }
}
