<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\TreasuryTransferApproved;
use App\Events\TreasuryTransferCreated;
use App\Events\TreasuryTransferSubmitted;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;

class TreasuryTransferService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryScopeService $scope,
        private BankAccountAccessService $bankAccess,
        private CashBoxCustodianService $custodians,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data): TreasuryTransfer
    {
        return DB::transaction(function () use ($data) {
            $this->validateEndpoints($data);
            $transfer = new TreasuryTransfer($data);
            $transfer->forceFill([
                'company_id' => $this->tenant->companyId(),
                'document_number' => $this->numbers->next(
                    'treasury_transfer', $this->tenant->companyId(), (int) $data['branch_id'], $data['transfer_date']
                ),
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.transfer.created', $transfer);
            DB::afterCommit(fn () => event(new TreasuryTransferCreated($transfer->id)));

            return $transfer;
        });
    }

    public function update(TreasuryTransfer $transfer, array $data): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            $transfer = $this->locked($transfer);
            if ($transfer->status !== 'draft') {
                throw new BusinessRuleException('Only draft treasury transfer can be edited.');
            }
            $this->validateEndpoints($data);
            $transfer->fill($data)->save();
            $this->audit->record('treasury.transfer.updated', $transfer);

            return $transfer;
        });
    }

    public function action(TreasuryTransfer $transfer, string $action, string $reason = ''): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $action, $reason) {
            $transfer = $this->locked($transfer);
            $transitions = [
                'submit' => ['draft', 'pending_approval', 'submitted_by', 'submitted_at', TreasuryTransferSubmitted::class],
                'approve' => ['pending_approval', 'approved', 'approved_by', 'approved_at', TreasuryTransferApproved::class],
                'cancel' => [['draft', 'pending_approval', 'approved'], 'cancelled', 'cancelled_by', 'cancelled_at', null],
            ];
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported treasury transfer action.');
            }
            [$from, $to, $actorField, $timeField, $event] = $transitions[$action];
            if (! in_array($transfer->status, (array) $from, true)) {
                throw new BusinessRuleException('Invalid treasury transfer status transition.');
            }
            if ($action === 'approve' && $transfer->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Transfer creator cannot approve the same transfer.');
            }
            if ($action === 'cancel' && blank($reason)) {
                throw new BusinessRuleException('Cancellation reason is required.');
            }
            $transfer->forceFill([
                'status' => $to, $actorField => $this->tenant->user()->id, $timeField => now(),
            ])->save();
            $this->audit->record('treasury.transfer.'.$action, $transfer, ['reason' => $reason]);
            if ($event) {
                DB::afterCommit(fn () => event(new $event($transfer->id)));
            }

            return $transfer;
        });
    }

    private function validateEndpoints(array $data): void
    {
        $branch = $this->scope->branch((int) $data['branch_id']);
        $this->scope->branch($data['destination_branch_id'] ?? null);
        $this->scope->currency((int) $data['currency_id']);
        if (bccomp((string) $data['amount'], '0', 4) !== 1
            || bccomp((string) ($data['exchange_rate'] ?? 1), '1', 8) !== 0
            || bccomp((string) ($data['fees_amount'] ?? 0), '0', 4) === -1) {
            throw new BusinessRuleException('Transfer amount or fees are invalid; cross-currency is not supported.');
        }
        $from = $this->endpoint($data, 'from', $branch->id, (string) $data['amount']);
        $to = $this->endpoint($data, 'to', $data['destination_branch_id'] ?? $branch->id);
        if ($from['type'] === $to['type'] && $from['id'] === $to['id']) {
            throw new BusinessRuleException('Treasury transfer source and destination must differ.');
        }
        if ($from['currency_id'] !== (int) $data['currency_id'] || $to['currency_id'] !== (int) $data['currency_id']) {
            throw new BusinessRuleException('Transfer endpoint currencies must match the transfer currency.');
        }
        $type = $data['transfer_type'] ?? 'transfer';
        if (($type === 'cash_deposit' && ! ($from['type'] === 'cash_box' && $to['type'] === 'bank'))
            || ($type === 'cash_withdrawal' && ! ($from['type'] === 'bank' && $to['type'] === 'cash_box'))) {
            throw new BusinessRuleException('Cash deposit or withdrawal transfer endpoints are invalid.');
        }
    }

    private function endpoint(array $data, string $side, int $branchId, ?string $amount = null): array
    {
        $type = $data[$side.'_type'];
        if ($type === 'bank') {
            $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'active')->findOrFail($data[$side.'_bank_account_id']);
            $this->bankAccess->assert($account, $branchId, 'can_transfer', $amount);

            return ['type' => 'bank', 'id' => $account->id, 'currency_id' => $account->currency_id];
        }
        if ($type === 'cash_box') {
            $box = CashBox::query()->where('company_id', $this->tenant->companyId())
                ->where('branch_id', $branchId)->where('status', 'active')->findOrFail($data[$side.'_cash_box_id']);
            if ($side === 'from') {
                $this->custodians->assert($box, 'can_transfer', $amount);
            }

            return ['type' => 'cash_box', 'id' => $box->id, 'currency_id' => $box->currency_id];
        }
        throw new BusinessRuleException('Treasury transfer endpoint type is invalid.');
    }

    private function locked(TreasuryTransfer $transfer): TreasuryTransfer
    {
        return TreasuryTransfer::query()->where('company_id', $this->tenant->companyId())
            ->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
    }
}
