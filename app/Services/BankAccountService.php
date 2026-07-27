<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankAccountActivated;
use App\Events\BankAccountClosed;
use App\Events\BankAccountCreated;
use App\Events\BankAccountSuspended;
use App\Models\BankAccount;
use App\Models\BankAdjustment;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementImport;
use App\Models\JournalEntryLine;
use App\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryScopeService $scope,
        private AuditService $audit
    ) {
    }

    public function create(array $data): BankAccount
    {
        return DB::transaction(function () use ($data) {
            $this->validate($data);
            $prepared = $this->prepared($data);
            $this->assertIbanUnique($prepared['iban_hash']);
            $account = new BankAccount($prepared);
            $account->forceFill([
                'company_id' => $this->tenant->companyId(), 'status' => 'draft',
                'created_by' => $this->tenant->user()->id, 'iban_hash' => $prepared['iban_hash'],
            ])->save();
            $this->audit->record('treasury.bank_account.created', $account);
            DB::afterCommit(fn () => event(new BankAccountCreated($account->id)));

            return $account;
        });
    }

    public function update(BankAccount $account, array $data): BankAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $account = $this->locked($account);
            if ($account->status === 'closed') {
                throw new BusinessRuleException('Closed bank account cannot be changed.');
            }
            $this->validate($data);
            $hasMovements = JournalEntryLine::query()->where('account_id', $account->gl_account_id)->exists()
                || BankStatementImport::query()->where('bank_account_id', $account->id)->exists()
                || BankReconciliationSession::query()->where('bank_account_id', $account->id)->exists();
            if ($hasMovements && ((int) $data['gl_account_id'] !== $account->gl_account_id
                || (int) $data['currency_id'] !== $account->currency_id)) {
                throw new BusinessRuleException('GL account and currency are locked after financial activity.');
            }
            $prepared = $this->prepared($data);
            $this->assertIbanUnique($prepared['iban_hash'], $account->id);
            $account->fill($prepared);
            if ($account->status === 'active' && ! empty($prepared['is_primary'])) {
                BankAccount::query()->where('company_id', $account->company_id)
                    ->where('currency_id', $account->currency_id)->whereKeyNot($account->id)
                    ->lockForUpdate()->update(['is_primary' => false]);
            }
            $account->forceFill([
                'updated_by' => $this->tenant->user()->id, 'iban_hash' => $prepared['iban_hash'],
            ])->save();
            $this->audit->record('treasury.bank_account.updated', $account);

            return $account;
        });
    }

    public function action(BankAccount $account, string $action, string $reason): BankAccount
    {
        return DB::transaction(function () use ($account, $action, $reason) {
            $account = $this->locked($account);
            $transitions = [
                'activate' => [['draft', 'suspended'], 'active', BankAccountActivated::class],
                'suspend' => [['active'], 'suspended', BankAccountSuspended::class],
                'close' => [['draft', 'active', 'suspended'], 'closed', BankAccountClosed::class],
            ];
            if (! isset($transitions[$action]) || blank($reason)) {
                throw new BusinessRuleException('Invalid bank account action or reason.');
            }
            [$from, $to, $event] = $transitions[$action];
            if (! in_array($account->status, $from, true)) {
                throw new BusinessRuleException('Invalid bank account status transition.');
            }
            if ($action === 'close' && ($this->hasPendingTransfers($account) || $this->hasPendingReconciliation($account))) {
                throw new BusinessRuleException('Pending treasury or reconciliation records block bank account closure.');
            }
            if ($to === 'active' && $account->is_primary) {
                BankAccount::query()->where('company_id', $account->company_id)
                    ->where('currency_id', $account->currency_id)->whereKeyNot($account->id)
                    ->lockForUpdate()->update(['is_primary' => false]);
            }
            $account->forceFill([
                'status' => $to, 'updated_by' => $this->tenant->user()->id,
                'closing_date' => $to === 'closed' ? now()->toDateString() : $account->closing_date,
                'closed_by' => $to === 'closed' ? $this->tenant->user()->id : null,
                'closed_at' => $to === 'closed' ? now() : null,
            ])->save();
            $this->audit->record('treasury.bank_account.'.$action, $account, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new $event($account->id)));

            return $account;
        });
    }

    private function validate(array $data): void
    {
        $this->scope->bank((int) $data['bank_id']);
        $this->scope->branch($data['branch_id'] ?? null);
        $this->scope->currency((int) $data['currency_id']);
        $this->scope->account((int) $data['gl_account_id'], 'bank');
        foreach (['bank_fees_account_id', 'interest_income_account_id', 'interest_expense_account_id',
            'unidentified_receipts_account_id', 'unidentified_payments_account_id'] as $field) {
            if (! empty($data[$field])) {
                $this->scope->account((int) $data[$field]);
            }
        }
    }

    private function prepared(array $data): array
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) ($data['iban'] ?? '')));
        $data['iban'] = $iban ?: null;
        $data['iban_hash'] = $iban ? hash('sha256', $iban) : null;
        if (! empty($data['account_number_masked'])) {
            $number = preg_replace('/\s+/', '', (string) $data['account_number_masked']);
            $data['account_number_masked'] = str_repeat('•', max(strlen($number) - 4, 4)).substr($number, -4);
        }

        return $data;
    }

    private function locked(BankAccount $account): BankAccount
    {
        return BankAccount::query()->where('company_id', $this->tenant->companyId())
            ->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }

    private function assertIbanUnique(?string $hash, ?int $exceptId = null): void
    {
        if ($hash && BankAccount::query()->where('company_id', $this->tenant->companyId())
            ->where('iban_hash', $hash)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw new BusinessRuleException('IBAN already exists in the current company.');
        }
    }

    private function hasPendingTransfers(BankAccount $account): bool
    {
        return TreasuryTransfer::query()->where('company_id', $account->company_id)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'ready_for_processing'])
            ->where(function ($query) use ($account) {
                $query->where('from_bank_account_id', $account->id)->orWhere('to_bank_account_id', $account->id);
            })->exists();
    }

    private function hasPendingReconciliation(BankAccount $account): bool
    {
        return BankStatementImport::query()->where('bank_account_id', $account->id)
            ->whereIn('status', ['uploaded', 'validating', 'validated', 'importing', 'partially_imported'])->exists()
            || BankReconciliationSession::query()->where('bank_account_id', $account->id)
                ->whereNotIn('status', ['completed', 'cancelled'])->exists()
            || BankAdjustment::query()->where('bank_account_id', $account->id)
                ->whereNotIn('status', ['posted', 'reversed', 'cancelled'])->exists();
    }
}
