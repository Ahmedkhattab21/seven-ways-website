<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CashBoxSessionOpened;
use App\Models\AccountingSetting;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\CashBoxSession;
use App\Models\CashOverShortAdjustment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CashBoxSessionService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryBalanceService $balances,
        private AccountingPeriodResolver $periods,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function open(array $data): CashBoxSession
    {
        return DB::transaction(function () use ($data) {
            $box = CashBox::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'active')->whereKey($data['cash_box_id'])->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($box->branch)) {
                throw new AuthorizationException('Cash box branch is outside the actor scope.');
            }
            $custodian = CashBoxCustodian::query()->where('cash_box_id', $box->id)
                ->where('user_id', $data['custodian_user_id'])->where('is_active', true)
                ->whereDate('valid_from', '<=', $data['business_date'])
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $data['business_date']))
                ->lockForUpdate()->first();
            if (! $custodian && ! $this->tenant->user()->hasPermission('treasury.cash_sessions.override_custodian')) {
                throw new BusinessRuleException('A valid cash box custodian assignment is required.');
            }
            $this->periods->resolve(
                $box->company_id, $data['business_date'], 'treasury', $this->tenant->user()
            );
            if (CashBoxSession::query()->where('cash_box_id', $box->id)
                ->where('active_guard', 'active')->lockForUpdate()->exists()) {
                throw new BusinessRuleException('Cash box already has an active session.');
            }
            $book = $this->balances->cashBox($box)['book_balance'];
            $session = new CashBoxSession([
                'branch_id' => $box->branch_id, 'cash_box_id' => $box->id,
                'custodian_user_id' => $data['custodian_user_id'],
                'business_date' => $data['business_date'], 'opening_notes' => $data['opening_notes'] ?? null,
            ]);
            $session->forceFill([
                'company_id' => $box->company_id,
                'session_number' => $this->numbers->next(
                    'cash_box_session', $box->company_id, $box->branch_id, $data['business_date']
                ),
                'status' => 'opened', 'active_guard' => 'active',
                'opening_book_balance' => $book, 'opening_counted_balance' => 0,
                'opening_difference' => bcmul($book, '-1', 4),
                'opened_by' => $this->tenant->user()->id, 'opened_at' => now(),
            ])->save();
            $this->audit->record('treasury.cash_session.opened', $session);
            DB::afterCommit(fn () => event(new CashBoxSessionOpened($session->id)));

            return $session;
        });
    }

    public function action(CashBoxSession $session, string $action, ?string $notes = null): CashBoxSession
    {
        return DB::transaction(function () use ($session, $action, $notes) {
            $session = CashBoxSession::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($session->cashBox->branch)) {
                throw new AuthorizationException('Cash session branch is outside the actor scope.');
            }
            if ($action === 'reopen') {
                if ($session->status !== 'closed') {
                    throw new BusinessRuleException('Only a closed cash session can be reopened.');
                }
                $session->cashBox()->lockForUpdate()->firstOrFail();
                if (CashBoxSession::query()->where('cash_box_id', $session->cash_box_id)
                    ->where('active_guard', 'active')->whereKeyNot($session->id)->lockForUpdate()->exists()) {
                    throw new BusinessRuleException('Another cash session is already active.');
                }
                $this->periods->resolve(
                    $session->company_id, $session->business_date->toDateString(),
                    'treasury', $this->tenant->user(), $notes
                );
                $session->forceFill(['status' => 'counting', 'active_guard' => 'active'])->save();
                $this->audit->record('treasury.cash_session.reopened', $session, ['reason' => $notes]);

                return $session;
            }
            if ($session->status === 'closed') {
                throw new BusinessRuleException('Closed cash sessions are immutable.');
            }
            $transitions = [
                'start_counting' => ['opened', 'counting', 'counting_started_by', 'counting_started_at'],
                'submit' => ['counting', 'pending_approval', 'submitted_by', 'submitted_at'],
                'approve' => ['pending_approval', 'approved', 'approved_by', 'approved_at'],
                'close' => ['approved', 'closed', 'closed_by', 'closed_at'],
                'cancel' => ['opened', 'cancelled', 'cancelled_by', 'cancelled_at'],
            ];
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported cash session action.');
            }
            [$from, $to, $actor, $time] = $transitions[$action];
            if ($session->status !== $from) {
                throw new BusinessRuleException('Invalid cash session status transition.');
            }
            if (in_array($action, ['submit', 'approve', 'close'], true)
                && ! $this->approvedClosingCount($session)) {
                throw new BusinessRuleException('لا يمكن إرسال الجلسة أو اعتمادها أو إغلاقها قبل تسجيل ومراجعة واعتماد العد الختامي.');
            }
            if ($action === 'approve') {
                $settings = AccountingSetting::query()->where('company_id', $session->company_id)->first();
                if ($settings?->separation_of_duties && $session->custodian_user_id === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Session custodian cannot approve the same session.');
                }
            }
            $changes = ['status' => $to, $actor => $this->tenant->user()->id, $time => now()];
            if ($action === 'close') {
                $closing = $this->approvedClosingCount($session);
                if (bccomp((string) $closing->difference, '0', 4) !== 0
                    && ! CashOverShortAdjustment::query()->where('cash_box_count_id', $closing->id)
                        ->where('status', 'posted')->exists()) {
                    throw new BusinessRuleException('Cash difference must be approved and posted before closing.');
                }
                $changes += [
                    'closing_book_balance' => $closing->book_total,
                    'closing_counted_balance' => $closing->counted_total,
                    'closing_difference' => $closing->difference, 'active_guard' => null,
                    'closing_notes' => $notes,
                ];
            } elseif ($action === 'cancel') {
                $changes['active_guard'] = null;
            }
            $session->forceFill($changes)->save();
            $this->audit->record('treasury.cash_session.'.$action, $session);
            $event = match ($action) {
                'submit' => \App\Events\CashBoxSessionSubmitted::class,
                'approve' => \App\Events\CashBoxSessionApproved::class,
                'close' => \App\Events\CashBoxSessionClosed::class,
                default => null,
            };
            if ($event) {
                DB::afterCommit(fn () => event(new $event($session->id)));
            }

            return $session;
        });
    }

    private function approvedClosingCount(CashBoxSession $session): ?\App\Models\CashBoxCount
    {
        return $session->counts()->where('count_type', 'closing')
            ->where('status', 'approved')->latest('id')->first();
    }
}
