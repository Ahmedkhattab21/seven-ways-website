<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\OperationalDepositConverted;
use App\Models\AppointmentDeposit;
use App\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

class OperationalDepositConversionService
{
    public function __construct(
        private TenantContext $tenant,
        private CustomerPaymentService $payments,
        private PaymentAllocationService $allocations,
        private AuditService $audit
    ) {
    }

    public function convert(AppointmentDeposit $deposit, ?\App\Models\SalesInvoice $invoice = null): CustomerPayment
    {
        return DB::transaction(function () use ($deposit, $invoice) {
            $deposit = AppointmentDeposit::query()->whereKey($deposit->id)->lockForUpdate()->with('appointment')->firstOrFail();
            abort_unless($deposit->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($deposit->appointment->branch), 403);
            if ($deposit->status !== 'recorded' || $deposit->converted_payment_id) {
                throw new BusinessRuleException('Deposit is cancelled or already converted.');
            }
            $payment = $this->payments->record([
                'customer_id' => $deposit->appointment->customer_id, 'payment_method_id' => $deposit->payment_method_id,
                'payment_date' => $deposit->received_at->toDateString(), 'amount' => $deposit->amount,
                'reference_number' => $deposit->reference_number, 'source_type' => 'appointment_deposit',
                'appointment_deposit_id' => $deposit->id,
            ]);
            $payment->forceFill(['status' => 'approved', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now()])->save();
            $deposit->forceFill(['converted_payment_id' => $payment->id, 'converted_at' => now()])->save();
            if ($invoice) {
                $this->allocations->allocate($payment, $invoice, min((float) $payment->amount, (float) $invoice->balance_due));
            }
            $this->audit->record('appointment_deposit.converted', $deposit, ['payment_id' => $payment->id]);
            DB::afterCommit(fn () => event(new OperationalDepositConverted($deposit->id, $payment->id)));

            return $payment->refresh();
        });
    }
}
