<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankReconciliationReopened;
use App\Models\BankAccount;
use App\Models\BankReconciliationSession;
use Illuminate\Support\Facades\DB;

class BankReconciliationReopenService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function reopen(BankReconciliationSession $session, string $reason): BankReconciliationSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $session = BankReconciliationSession::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'completed' || blank($reason)) {
                throw new BusinessRuleException('Only completed reconciliation can be reopened with a reason.');
            }
            if ($session->completed_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Reconciliation completer cannot reopen the same session.');
            }
            if (BankReconciliationSession::query()->where('bank_account_id', $session->bank_account_id)
                ->where('status', 'completed')->whereDate('date_to', '>', $session->date_to)->exists()) {
                throw new BusinessRuleException('A later completed reconciliation blocks reopen.');
            }
            $session->forceFill([
                'status' => 'reopened', 'reopened_by' => $this->tenant->user()->id,
                'reopened_at' => now(), 'reason' => $reason,
                'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null,
            ])->save();
            $last = BankReconciliationSession::query()->where('bank_account_id', $session->bank_account_id)
                ->where('status', 'completed')->where('id', '!=', $session->id)->max('date_to');
            BankAccount::query()->whereKey($session->bank_account_id)->update(['last_reconciled_date' => $last]);
            $this->audit->record('bank_reconciliation.reopened', $session, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new BankReconciliationReopened($session->id)));

            return $session;
        });
    }
}
