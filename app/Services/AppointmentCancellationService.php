<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentMarkedNoShow;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function cancel(Appointment $appointment, string $reason, ?string $depositDecision = null): Appointment
    {
        return $this->transition($appointment, 'cancelled', $reason, $depositDecision);
    }

    public function noShow(Appointment $appointment, string $reason, ?string $depositDecision = null): Appointment
    {
        if ($appointment->scheduled_start->isFuture()) {
            throw new BusinessRuleException('A future appointment cannot be marked no-show.');
        }

        return $this->transition($appointment, 'no_show', $reason, $depositDecision);
    }

    private function transition(Appointment $appointment, string $status, string $reason, ?string $depositDecision): Appointment
    {
        return DB::transaction(function () use ($appointment, $status, $reason, $depositDecision) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($locked->branch)) {
                throw new BusinessRuleException('Appointment is outside your scope.', status: 403);
            }
            if (in_array($locked->status, ['completed', 'cancelled', 'no_show'], true)) {
                throw new BusinessRuleException('Appointment cannot be cancelled from its current status.');
            }
            $depositStatus = match ($depositDecision) {
                'refunded' => 'refunded', 'forfeited' => 'forfeited', default => $locked->deposit_status,
            };
            $locked->forceFill([
                'status' => $status, 'deposit_status' => $depositStatus,
                $status === 'cancelled' ? 'cancellation_reason' : 'no_show_reason' => $reason,
                'cancelled_by' => $this->tenant->user()?->id, 'cancelled_at' => now(),
            ])->save();
            $this->audit->record("appointment.{$status}", $locked, [
                'deposit_decision' => $depositDecision ?: 'pending_decision', 'financial_refund_created' => false,
            ]);
            $event = $status === 'cancelled' ? AppointmentCancelled::class : AppointmentMarkedNoShow::class;
            DB::afterCommit(fn () => event(new $event($locked->id)));

            return $locked;
        });
    }
}
