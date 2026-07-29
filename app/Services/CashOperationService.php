<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashPayment;
use App\Models\CashReceipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashOperationService
{
    public function __construct(
        private TenantContext $tenant,
        private CashBoxCustodianService $custodians,
        private TreasuryOperationAuthorizationService $authorization,
        private CashSessionOperationalGuard $sessionGuard,
        private DocumentNumberService $numbers,
        private CashOperationPostingService $posting,
        private AuditService $audit
    ) {
    }

    public function create(string $direction, array $data): CashReceipt|CashPayment
    {
        return DB::transaction(function () use ($direction, $data) {
            $class = $this->class($direction);
            $box = CashBox::query()->where('company_id', $this->tenant->companyId())
                ->where('branch_id', $data['branch_id'])->where('status', 'active')
                ->lockForUpdate()->findOrFail($data['cash_box_id']);
            if ($box->currency_id !== (int) $data['currency_id'] || bccomp((string) ($data['exchange_rate'] ?? 1), '1', 8) !== 0) {
                throw new BusinessRuleException('Cross-currency cash operations are not supported.');
            }
            if (($direction === 'receipt' && ! $box->allows_receipts)
                || ($direction === 'payment' && ! $box->allows_payments)) {
                throw new BusinessRuleException('Cash box does not allow this operation.');
            }
            $sessionId = $data['cash_box_session_id'] ?? null;
            if ($box->requires_shift_opening) {
                $sessionId = $this->sessionGuard->assertReady($box, $sessionId)->id;
            }
            $this->custodians->assert($box, $direction === 'receipt' ? 'can_receive' : 'can_pay', (string) $data['amount']);
            $this->authorization->assert(
                'treasury.cash_'.$direction.'s.create', 'cash_'.$direction, 'create',
                $box->currency_id, (string) $data['amount'], $box->branch_id
            );
            $offset = Account::query()->where('company_id', $box->company_id)->where('is_active', true)
                ->where('is_posting', true)->findOrFail($data['offset_account_id']);
            if ($offset->is_control_account
                && ! $this->tenant->user()->hasPermission('accounting.journals.post_control_accounts')) {
                throw new BusinessRuleException('Control account cash operation requires explicit permission.');
            }
            $operation = new $class($data);
            $operation->forceFill([
                'company_id' => $box->company_id, 'cash_box_session_id' => $sessionId,
                'document_number' => $this->numbers->next(
                    'cash_'.$direction, $box->company_id, $box->branch_id, $data['document_date']
                ),
                'status' => 'draft', 'idempotency_key' => (string) Str::uuid(),
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.cash_'.$direction.'.created', $operation);

            return $operation;
        });
    }

    public function action(Model $operation, string $action, ?string $reason = null, ?string $date = null): Model
    {
        return DB::transaction(function () use ($operation, $action, $reason, $date) {
            if (! $operation instanceof CashReceipt && ! $operation instanceof CashPayment) {
                throw new BusinessRuleException('Unsupported cash operation.');
            }
            $operation = $operation->newQuery()->where('company_id', $this->tenant->companyId())
                ->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $direction = $operation instanceof CashReceipt ? 'receipt' : 'payment';
            if ($action === 'post') {
                $box = CashBox::query()->where('company_id', $operation->company_id)
                    ->whereKey($operation->cash_box_id)->firstOrFail();
                $this->sessionGuard->assertReady($box, $operation->cash_box_session_id);
            }
            $ability = in_array($action, ['submit', 'approve', 'post'], true) ? $action : 'post';
            $this->authorization->assert(
                'treasury.cash_'.$direction.'s.'.$action, 'cash_'.$direction, $ability,
                $operation->currency_id, (string) $operation->amount, $operation->branch_id,
                $operation->created_by
            );
            if ($action === 'reverse') {
                if ($operation->status !== 'posted') {
                    throw new BusinessRuleException('Only posted cash operations can be reversed.');
                }
                $entry = $this->posting->reverse($operation, (string) $reason, $date);
                $operation->forceFill([
                    'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $entry->id,
                ])->save();
            } else {
                $transitions = [
                    'submit' => ['draft', 'pending_approval', 'submitted_by'],
                    'approve' => ['pending_approval', 'approved', 'approved_by'],
                    'post' => ['approved', 'posted', 'posted_by'],
                    'cancel' => ['draft', 'cancelled', null],
                ];
                if (! isset($transitions[$action])) {
                    throw new BusinessRuleException('Unsupported cash operation action.');
                }
                [$from, $to, $actor] = $transitions[$action];
                if ($operation->status !== $from) {
                    throw new BusinessRuleException('Invalid cash operation transition.');
                }
                $changes = ['status' => $to];
                if ($actor) {
                    $changes[$actor] = $this->tenant->user()->id;
                }
                if ($action === 'approve' && $operation->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Cash operation creator cannot approve it.');
                }
                if ($action === 'post') {
                    $entry = $this->posting->post($operation);
                    $changes['journal_entry_id'] = $entry->id;
                }
                $operation->forceFill($changes)->save();
            }
            $this->audit->record('treasury.'.Str::snake(class_basename($operation)).'.'.$action, $operation);
            $event = match ([$operation::class, $action]) {
                [CashReceipt::class, 'post'] => \App\Events\CashReceiptPosted::class,
                [CashReceipt::class, 'reverse'] => \App\Events\CashReceiptReversed::class,
                [CashPayment::class, 'post'] => \App\Events\CashPaymentPosted::class,
                [CashPayment::class, 'reverse'] => \App\Events\CashPaymentReversed::class,
                default => null,
            };
            if ($event) {
                DB::afterCommit(fn () => event(new $event($operation->id)));
            }

            return $operation;
        });
    }

    private function class(string $direction): string
    {
        return match ($direction) {
            'receipt' => CashReceipt::class,
            'payment' => CashPayment::class,
            default => throw new BusinessRuleException('Unsupported cash operation direction.'),
        };
    }
}
