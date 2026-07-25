<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\DepositRecorded;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class AppointmentDepositService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function record(Appointment $appointment, array $data): AppointmentDeposit
    {
        $this->assertScope($appointment);
        $payment = PaymentMethod::query()->whereKey($data['payment_method_id'])
            ->where('company_id', $appointment->company_id)->where('is_active', true)->firstOrFail();
        if ($payment->requires_reference && empty($data['reference_number'])) {
            throw new BusinessRuleException('Payment method reference is required.');
        }
        if (bccomp((string) $data['amount'], '0', 4) !== 1) {
            throw new BusinessRuleException('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($appointment, $data) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $recorded = $locked->deposits()->where('status', 'recorded')->sum('amount');
            $newTotal = bcadd((string) $recorded, (string) $data['amount'], 4);
            if (bccomp($newTotal, $locked->deposit_amount, 4) === 1
                && ! $this->tenant->user()?->hasPermission('appointment_deposits.refund_status')) {
                throw new BusinessRuleException('Deposit exceeds the required amount.');
            }
            $deposit = new AppointmentDeposit($data);
            $deposit->forceFill([
                'company_id' => $locked->company_id, 'branch_id' => $locked->branch_id,
                'appointment_id' => $locked->id, 'receipt_number' => $this->numbers->next(
                    'appointment_deposit', $locked->company_id, $locked->branch_id, $data['received_at']
                ),
                'status' => 'recorded', 'received_by' => $this->tenant->user()?->id,
            ])->save();
            $locked->forceFill([
                'deposit_status' => bccomp($newTotal, $locked->deposit_amount, 4) >= 0 ? 'paid' : 'partial',
            ])->save();
            $this->audit->record('appointment_deposit.recorded', $deposit, ['operational_only' => true]);
            DB::afterCommit(fn () => event(new DepositRecorded($deposit->id)));

            return $deposit;
        });
    }

    public function cancel(AppointmentDeposit $deposit, string $reason): AppointmentDeposit
    {
        return DB::transaction(function () use ($deposit, $reason) {
            $locked = AppointmentDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            $this->assertScope($locked->appointment);
            if ($locked->status !== 'recorded') {
                throw new BusinessRuleException('Only a recorded operational deposit can be cancelled.');
            }
            $locked->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()?->id,
                'cancelled_at' => now(), 'notes' => trim(($locked->notes ? $locked->notes."\n" : '').$reason),
            ])->save();
            $this->syncAppointmentStatus($locked->appointment);
            $this->audit->record('appointment_deposit.cancelled', $locked, ['operational_only' => true]);

            return $locked;
        });
    }

    private function syncAppointmentStatus(Appointment $appointment): void
    {
        $paid = $appointment->deposits()->where('status', 'recorded')->sum('amount');
        $appointment->forceFill(['deposit_status' => bccomp((string) $paid, '0', 4) === 0
            ? ($appointment->deposit_required ? 'pending' : 'not_required')
            : (bccomp((string) $paid, $appointment->deposit_amount, 4) >= 0 ? 'paid' : 'partial')])->save();
    }

    private function assertScope(Appointment $appointment): void
    {
        if ($appointment->company_id !== $this->tenant->companyId()
            || ! $this->tenant->user()?->canAccessBranch($appointment->branch)) {
            throw new BusinessRuleException('Appointment is outside your scope.', status: 403);
        }
    }
}
