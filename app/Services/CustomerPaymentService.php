<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CustomerPaymentApproved;
use App\Events\CustomerPaymentRecorded;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function record(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::whereKey($data['customer_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
            $method = PaymentMethod::whereKey($data['payment_method_id'])->where('company_id', $customer->company_id)->where('is_active', true)->firstOrFail();
            if (bccomp((string) $data['amount'], '0', 4) !== 1 || ($method->requires_reference && empty($data['reference_number']))) {
                throw new BusinessRuleException('A positive amount and required payment reference are mandatory.');
            }
            $payment = new CustomerPayment($data);
            $payment->forceFill([
                'company_id' => $customer->company_id, 'branch_id' => $this->tenant->branchId(),
                'payment_number' => $this->numbers->next('customer_payment', $customer->company_id, $this->tenant->branchId(), $data['payment_date']),
                'currency_id' => $data['currency_id'] ?? $this->tenant->company()->currency_id,
                'status' => 'recorded', 'allocated_amount' => 0, 'unallocated_amount' => $data['amount'],
                'source_type' => $data['source_type'] ?? 'manual', 'received_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('customer_payment.recorded', $payment);
            DB::afterCommit(fn () => event(new CustomerPaymentRecorded($payment->id)));

            return $payment;
        });
    }

    public function approve(CustomerPayment $payment): CustomerPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = CustomerPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($payment->branch), 403);
            if ($payment->status !== 'recorded') {
                throw new BusinessRuleException('Only recorded payments can be approved.');
            }
            $payment->forceFill(['status' => 'approved', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now()])->save();
            $this->audit->record('customer_payment.approved', $payment);
            DB::afterCommit(fn () => event(new CustomerPaymentApproved($payment->id)));

            return $payment;
        });
    }
}
