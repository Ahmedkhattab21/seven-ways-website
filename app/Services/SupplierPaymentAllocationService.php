<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierPaymentAllocated;
use App\Events\SupplierPaymentAllocationReversed;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Facades\DB;

class SupplierPaymentAllocationService
{
    public function __construct(
        private TenantContext $tenant,
        private SupplierInvoiceBalanceService $balances,
        private AuditService $audit
    ) {
    }

    public function allocate(SupplierPayment $payment, SupplierInvoice $invoice, string $amount): SupplierPaymentAllocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amount) {
            $payment = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertPair($payment, $invoice);
            $this->rebuildPayment($payment);
            $invoice = $this->balances->rebuild($invoice);
            if (! in_array($payment->status, ['processed', 'partially_allocated'], true)
                || bccomp($amount, '0', 4) !== 1
                || bccomp($amount, $payment->unallocated_amount, 4) === 1
                || bccomp($amount, $invoice->balance_due, 4) === 1) {
                throw new BusinessRuleException('Supplier allocation exceeds an available balance.');
            }
            $allocation = new SupplierPaymentAllocation([
                'supplier_payment_id' => $payment->id,
                'supplier_invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);
            $allocation->forceFill([
                'company_id' => $payment->company_id, 'allocated_at' => now(),
                'allocated_by' => $this->tenant->user()->id,
            ])->save();
            $this->rebuildPayment($payment);
            $this->balances->rebuild($invoice);
            $this->audit->record('supplier_payment.allocated', $allocation);
            DB::afterCommit(fn () => event(new SupplierPaymentAllocated($allocation->id)));

            return $allocation;
        });
    }

    public function reverse(SupplierPaymentAllocation $allocation, string $reason): SupplierPaymentAllocation
    {
        return DB::transaction(function () use ($allocation, $reason) {
            $allocation = SupplierPaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($allocation->reversed_at) {
                throw new BusinessRuleException('Supplier allocation is already reversed.');
            }
            $payment = SupplierPayment::whereKey($allocation->supplier_payment_id)->lockForUpdate()->firstOrFail();
            $invoice = SupplierInvoice::whereKey($allocation->supplier_invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertPair($payment, $invoice);
            $allocation->forceFill([
                'reversed_at' => now(), 'reversed_by' => $this->tenant->user()->id,
                'reversal_reason' => $reason,
            ])->save();
            $this->rebuildPayment($payment);
            $this->balances->rebuild($invoice);
            $this->audit->record('supplier_payment.allocation_reversed', $allocation);
            DB::afterCommit(fn () => event(new SupplierPaymentAllocationReversed($allocation->id)));

            return $allocation;
        });
    }

    private function rebuildPayment(SupplierPayment $payment): void
    {
        $allocated = (string) SupplierPaymentAllocation::where('supplier_payment_id', $payment->id)
            ->whereNull('reversed_at')->sum('amount');
        $payment->forceFill([
            'allocated_amount' => $allocated,
            'unallocated_amount' => bcsub($payment->amount, $allocated, 4),
            'status' => bccomp($allocated, '0', 4) === 0 ? 'processed'
                : (bccomp($allocated, $payment->amount, 4) >= 0 ? 'allocated' : 'partially_allocated'),
        ])->save();
    }

    private function assertPair(SupplierPayment $payment, SupplierInvoice $invoice): void
    {
        abort_unless(
            $payment->company_id === $this->tenant->companyId()
            && $invoice->company_id === $payment->company_id
            && $invoice->supplier_id === $payment->supplier_id
            && $invoice->currency_id === $payment->currency_id
            && $this->tenant->user()->canAccessBranch($invoice->branch),
            403
        );
    }
}
