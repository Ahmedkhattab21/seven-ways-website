<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierPaymentProcessed;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::whereKey($data['supplier_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
            PaymentMethod::whereKey($data['payment_method_id'])->where('company_id', $supplier->company_id)
                ->where('is_active', true)->firstOrFail();
            if (bccomp((string) $data['amount'], '0', 4) !== 1) {
                throw new BusinessRuleException('Supplier payment amount must be positive.');
            }
            $payment = new SupplierPayment($data);
            $payment->forceFill([
                'company_id' => $supplier->company_id, 'branch_id' => $this->tenant->branchId(),
                'payment_number' => $this->numbers->next(
                    'supplier_payment',
                    $supplier->company_id,
                    $this->tenant->branchId(),
                    $data['payment_date']
                ),
                'currency_id' => $data['currency_id'] ?? $supplier->currency_id ?? $this->tenant->company()->currency_id,
                'status' => 'draft', 'allocated_amount' => 0, 'unallocated_amount' => $data['amount'],
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('supplier_payment.created', $payment, ['operational_only' => true]);

            return $payment;
        });
    }

    public function approve(SupplierPayment $payment): SupplierPayment
    {
        return $this->transition($payment, 'draft', 'approved', 'approved');
    }

    public function process(SupplierPayment $payment): SupplierPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lockScoped($payment);
            if ($payment->status !== 'approved') {
                throw new BusinessRuleException('Only approved supplier payments can be processed.');
            }
            $payment->forceFill([
                'status' => 'processed', 'processed_by' => $this->tenant->user()->id, 'processed_at' => now(),
            ])->save();
            $this->audit->record('supplier_payment.processed', $payment, ['operational_only' => true]);
            DB::afterCommit(fn () => event(new SupplierPaymentProcessed($payment->id)));

            return $payment;
        });
    }

    private function transition(SupplierPayment $payment, string $from, string $to, string $action): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $from, $to, $action) {
            $payment = $this->lockScoped($payment);
            if ($payment->status !== $from) {
                throw new BusinessRuleException("Supplier payment must be {$from}.");
            }
            $payment->forceFill([
                'status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now(),
            ])->save();

            return $payment;
        });
    }

    private function lockScoped(SupplierPayment $payment): SupplierPayment
    {
        $payment = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
        abort_unless($payment->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($payment->branch), 403);

        return $payment;
    }
}
