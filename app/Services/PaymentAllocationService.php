<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PaymentAllocated;
use App\Events\PaymentAllocationReversed;
use App\Events\SalesInvoicePaid;
use App\Events\SalesInvoicePartiallyPaid;
use App\Models\CustomerPayment;
use App\Models\PaymentAllocation;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class PaymentAllocationService
{
    public function __construct(
        private TenantContext $tenant,
        private AuditService $audit,
        private SalesInvoiceBalanceService $balances
    ) {
    }

    public function allocate(CustomerPayment $payment, SalesInvoice $invoice, string $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amount) {
            $payment = CustomerPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertPair($payment, $invoice);
            $this->rebuildPayment($payment);
            $invoice = $this->balances->rebuild($invoice);
            if (! in_array($payment->status, ['approved', 'partially_allocated'], true)
                || bccomp($amount, '0', 4) !== 1
                || bccomp($amount, $payment->unallocated_amount, 4) === 1
                || bccomp($amount, $invoice->balance_due, 4) === 1) {
                throw new BusinessRuleException('Allocation exceeds the available payment or invoice balance.');
            }
            $allocation = new PaymentAllocation(['customer_payment_id' => $payment->id, 'sales_invoice_id' => $invoice->id, 'amount' => $amount]);
            $allocation->forceFill([
                'company_id' => $payment->company_id, 'allocated_at' => now(), 'allocated_by' => $this->tenant->user()->id,
            ])->save();
            $this->rebuildPayment($payment);
            $invoice = $this->balances->rebuild($invoice);
            $this->audit->record('payment.allocated', $allocation);
            DB::afterCommit(function () use ($allocation, $invoice) {
                event(new PaymentAllocated($allocation->id));
                event($invoice->fresh()->status === 'paid' ? new SalesInvoicePaid($invoice->id) : new SalesInvoicePartiallyPaid($invoice->id));
            });

            return $allocation;
        });
    }

    public function reverse(PaymentAllocation $allocation, string $reason): PaymentAllocation
    {
        return DB::transaction(function () use ($allocation, $reason) {
            $allocation = PaymentAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($allocation->reversed_at) {
                throw new BusinessRuleException('Allocation is already reversed.');
            }
            $payment = CustomerPayment::query()->whereKey($allocation->customer_payment_id)->lockForUpdate()->firstOrFail();
            $invoice = SalesInvoice::query()->whereKey($allocation->sales_invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertPair($payment, $invoice);
            $allocation->forceFill([
                'reversed_at' => now(), 'reversed_by' => $this->tenant->user()->id, 'reversal_reason' => $reason,
            ])->save();
            $this->rebuildPayment($payment);
            $this->balances->rebuild($invoice);
            $this->audit->record('payment.allocation_reversed', $allocation);
            DB::afterCommit(fn () => event(new PaymentAllocationReversed($allocation->id)));

            return $allocation;
        });
    }

    private function rebuildPayment(CustomerPayment $payment): void
    {
        $paymentAllocated = (string) PaymentAllocation::query()
            ->where('customer_payment_id', $payment->id)
            ->whereNull('reversed_at')
            ->sum('amount');
        $payment->forceFill([
            'allocated_amount' => $paymentAllocated,
            'unallocated_amount' => bcsub($payment->amount, $paymentAllocated, 4),
            'status' => bccomp($paymentAllocated, '0', 4) === 0 ? 'approved'
                : (bccomp($paymentAllocated, $payment->amount, 4) === 0 ? 'allocated' : 'partially_allocated'),
        ])->save();
    }

    private function assertPair(CustomerPayment $payment, SalesInvoice $invoice): void
    {
        abort_unless(
            $payment->company_id === $this->tenant->companyId()
            && $invoice->company_id === $payment->company_id
            && $invoice->branch_id === $payment->branch_id
            && $invoice->customer_id === $payment->customer_id
            && $invoice->currency_id === $payment->currency_id
            && $this->tenant->user()->canAccessBranch($invoice->branch),
            403
        );
    }
}
