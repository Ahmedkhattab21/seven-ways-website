<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankReconciliationApproved;
use App\Events\BankReconciliationCompleted;
use App\Events\BankReconciliationReviewed;
use App\Events\BankReconciliationStarted;
use App\Models\BankAccount;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementImport;
use Illuminate\Support\Facades\DB;

class BankReconciliationSessionService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingPeriodResolver $periods,
        private DocumentNumberService $numbers,
        private BankAccountAccessService $access,
        private BankReconciliationCalculationService $calculation,
        private BankReconciliationValidationService $validation,
        private AuditService $audit
    ) {
    }

    public function create(array $data): BankReconciliationSession
    {
        $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
            ->where('status', 'active')->findOrFail($data['bank_account_id']);
        $branchId = $account->branch_id ?: $this->tenant->branchId();
        $this->access->assert($account, (int) $branchId, 'can_view');
        $period = $this->periods->resolve(
            $account->company_id, $data['date_to'], 'treasury', $this->tenant->user(), $data['override_reason'] ?? null
        );
        if ($data['date_from'] > $data['date_to']
            || $data['date_from'] < $period->fiscalYear->start_date->toDateString()
            || $data['date_to'] > $period->fiscalYear->end_date->toDateString()) {
            throw new BusinessRuleException('Reconciliation date range must be inside the fiscal year.');
        }
        if (BankReconciliationSession::query()->where('bank_account_id', $account->id)
            ->where('status', 'completed')->whereDate('date_from', '<=', $data['date_to'])
            ->whereDate('date_to', '>=', $data['date_from'])->exists()) {
            throw new BusinessRuleException('A completed reconciliation overlaps this date range.');
        }
        $imports = BankStatementImport::query()->where('company_id', $account->company_id)
            ->where('bank_account_id', $account->id)->where('status', 'imported')
            ->whereIn('id', $data['import_ids'])->get();
        if ($imports->count() !== count(array_unique($data['import_ids']))
            || $imports->contains(fn ($import) => $import->period_start->toDateString() < $data['date_from']
                || $import->period_end->toDateString() > $data['date_to'])) {
            throw new BusinessRuleException('Every import must be imported, same-account, and inside the session range.');
        }
        if (DB::table('bank_reconciliation_session_imports')
            ->join('bank_reconciliation_sessions', 'bank_reconciliation_sessions.id', '=', 'bank_reconciliation_session_imports.bank_reconciliation_session_id')
            ->whereIn('bank_statement_import_id', $imports->pluck('id'))
            ->where('bank_reconciliation_sessions.status', 'completed')->exists()) {
            throw new BusinessRuleException('An import already used by completed reconciliation cannot be reused.');
        }

        return DB::transaction(function () use ($data, $account, $branchId, $period, $imports) {
            $session = new BankReconciliationSession($data);
            $session->forceFill([
                'company_id' => $account->company_id, 'bank_account_id' => $account->id,
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
                'session_number' => $this->numbers->next('bank_reconciliation', $account->company_id, $branchId, $data['date_to']),
                'statement_opening_balance' => '0', 'statement_closing_balance' => '0',
                'book_opening_balance' => '0', 'book_closing_balance' => '0',
                'status' => 'matching', 'started_by' => $this->tenant->user()->id, 'started_at' => now(),
            ])->save();
            foreach ($imports as $import) {
                DB::table('bank_reconciliation_session_imports')->insert([
                    'company_id' => $account->company_id, 'bank_reconciliation_session_id' => $session->id,
                    'bank_statement_import_id' => $import->id, 'created_at' => now(),
                ]);
            }
            $this->calculation->snapshot($session);
            $this->audit->record('bank_reconciliation.started', $session, ['import_ids' => $imports->pluck('id')->all()]);
            DB::afterCommit(fn () => event(new BankReconciliationStarted($session->id)));

            return $session->fresh(['imports', 'bankAccount']);
        });
    }

    public function action(BankReconciliationSession $session, string $action, array $data = []): BankReconciliationSession
    {
        return DB::transaction(function () use ($session, $action, $data) {
            $session = BankReconciliationSession::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status === 'completed' && $action === 'complete') {
                return $session;
            }
            $transitions = [
                'review' => [['matching', 'ready_for_review', 'reopened'], 'under_review', 'reviewed_by', 'reviewed_at', BankReconciliationReviewed::class],
                'approve' => [['under_review'], 'approved', 'approved_by', 'approved_at', BankReconciliationApproved::class],
                'cancel' => [['draft', 'matching', 'ready_for_review', 'under_review', 'approved', 'reopened'], 'cancelled', null, null, null],
            ];
            if ($action === 'complete') {
                return $this->complete($session);
            }
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported reconciliation action.');
            }
            [$from, $to, $actor, $time, $event] = $transitions[$action];
            if (! in_array($session->status, $from, true)) {
                throw new BusinessRuleException('Invalid reconciliation status transition.');
            }
            if ($action === 'review' && $session->started_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Session starter cannot review the same reconciliation.');
            }
            if ($action === 'approve' && in_array($this->tenant->user()->id, [$session->started_by, $session->reviewed_by], true)) {
                throw new BusinessRuleException('Separation of duties prevents reconciliation self-approval.');
            }
            if ($action === 'approve' && $session->imports()->where('uploaded_by', $this->tenant->user()->id)->exists()) {
                throw new BusinessRuleException('Statement importer cannot approve the same reconciliation.');
            }
            $changes = ['status' => $to];
            if ($actor) {
                $changes += [$actor => $this->tenant->user()->id, $time => now()];
            }
            if ($action === 'review') {
                $changes['review_notes'] = $data['notes'] ?? null;
                $this->calculation->snapshot($session);
            }
            if ($action === 'approve') {
                $changes['approval_notes'] = $data['notes'] ?? null;
            }
            if ($action === 'cancel') {
                $changes['reason'] = $data['reason'] ?? null;
            }
            $session->forceFill($changes)->save();
            $this->audit->record('bank_reconciliation.'.$action, $session, array_filter($data));
            if ($event) {
                DB::afterCommit(fn () => event(new $event($session->id)));
            }

            return $session;
        });
    }

    private function complete(BankReconciliationSession $session): BankReconciliationSession
    {
        if ($session->status !== 'approved') {
            throw new BusinessRuleException('Only an approved reconciliation can be completed.');
        }
        $session->bankAccount()->lockForUpdate()->firstOrFail();
        $session->matches()->whereIn('status', ['accepted', 'completed'])->lockForUpdate()->get();
        $this->calculation->snapshot($session);
        $this->validation->assertCompletable($session->fresh());
        $session->forceFill([
            'status' => 'completed', 'completed_by' => $this->tenant->user()->id, 'completed_at' => now(),
        ])->save();
        $session->matches()->where('status', 'accepted')->update(['status' => 'completed']);
        $session->bankAccount->forceFill(['last_reconciled_date' => $session->date_to])->save();
        $this->audit->record('bank_reconciliation.completed', $session);
        DB::afterCommit(fn () => event(new BankReconciliationCompleted($session->id)));

        return $session;
    }
}
