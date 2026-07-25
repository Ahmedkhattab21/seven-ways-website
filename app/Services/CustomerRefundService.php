<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CustomerRefundProcessed;
use App\Models\CustomerRefund;
use App\Models\PaymentMethod;
use App\Models\SalesCreditNote;
use Illuminate\Support\Facades\DB;

class CustomerRefundService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit,
        private SalesInvoiceBalanceService $balances
    ) {
    }

    public function create(array $data): CustomerRefund
    {
        return DB::transaction(function () use ($data) {
            $note = SalesCreditNote::whereKey($data['sales_credit_note_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
            $method = PaymentMethod::whereKey($data['payment_method_id'])->where('company_id', $note->company_id)->where('is_active', true)->firstOrFail();
            $refunded = (string) CustomerRefund::query()
                ->where('sales_credit_note_id', $note->id)
                ->where('status', 'processed')
                ->sum('amount');
            $available = bcsub(bcsub($note->total, $note->applied_amount, 4), $refunded, 4);
            if (! in_array($note->status, ['issued', 'partially_applied', 'applied'], true)
                || bccomp((string) $data['amount'], '0', 4) !== 1
                || bccomp((string) $data['amount'], $available, 4) === 1) {
                throw new BusinessRuleException('Refund requires available issued credit.');
            }
            $refund = new CustomerRefund($data);
            $refund->forceFill([
                'company_id' => $note->company_id, 'branch_id' => $note->branch_id,
                'refund_number' => $this->numbers->next('customer_refund', $note->company_id, $note->branch_id, $data['refund_date']),
                'customer_id' => $note->customer_id, 'status' => 'draft', 'processed_by' => $this->tenant->user()->id,
            ])->save();

            return $refund;
        });
    }

    public function approve(CustomerRefund $refund): CustomerRefund
    {
        return $this->transition($refund, 'draft', 'approved');
    }

    public function process(CustomerRefund $refund): CustomerRefund
    {
        return DB::transaction(function () use ($refund) {
            $refund = CustomerRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($refund->creditNote->invoice->branch), 403);
            if ($refund->status !== 'approved') {
                throw new BusinessRuleException('Only approved refunds can be processed.');
            }
            $note = SalesCreditNote::query()->whereKey($refund->sales_credit_note_id)->lockForUpdate()->firstOrFail();
            $available = bcsub($note->total, $note->refunded_amount, 4);
            if (bccomp($refund->amount, $available, 4) === 1) {
                throw new BusinessRuleException('Refund exceeds available credit.');
            }
            $invoice = $note->invoice()->lockForUpdate()->first();
            $refund->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
            $refunded = (string) CustomerRefund::query()
                ->where('sales_credit_note_id', $note->id)
                ->where('status', 'processed')
                ->sum('amount');
            $remaining = bcsub(bcsub($note->total, $note->applied_amount, 4), $refunded, 4);
            $note->forceFill([
                'refunded_amount' => $refunded,
                'remaining_amount' => $remaining,
                'status' => bccomp($remaining, '0', 4) === 0 ? 'refunded' : $note->status,
            ])->save();
            $this->balances->rebuild($invoice);
            $this->audit->record('customer_refund.processed', $refund, ['operational_only' => true]);
            DB::afterCommit(fn () => event(new CustomerRefundProcessed($refund->id)));

            return $refund;
        });
    }

    private function transition(CustomerRefund $refund, string $from, string $to): CustomerRefund
    {
        return DB::transaction(function () use ($refund, $from, $to) {
            $refund = CustomerRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->company_id === $this->tenant->companyId(), 403);
            if ($refund->status !== $from) {
                throw new BusinessRuleException("Refund must be {$from}.");
            }
            $refund->forceFill(['status' => $to, 'approved_by' => $this->tenant->user()->id, 'approved_at' => now()])->save();

            return $refund;
        });
    }
}
