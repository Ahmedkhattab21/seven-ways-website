<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AppointmentCheckedIn;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class AppointmentCheckInService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function checkIn(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($locked->branch)) {
                throw new BusinessRuleException('Appointment is outside your scope.', status: 403);
            }
            if (! in_array($locked->status, ['pending', 'confirmed'], true)) {
                throw new BusinessRuleException('Only pending or confirmed appointments can check in.');
            }
            $locked->forceFill([
                'status' => 'checked_in', 'checked_in_at' => now(),
                'arrival_notes' => $data['arrival_notes'] ?? null,
                'odometer_snapshot' => $data['odometer_snapshot'] ?? null,
                'updated_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record('appointment.checked_in', $locked, ['work_order_created' => false]);
            DB::afterCommit(fn () => event(new AppointmentCheckedIn($locked->id)));

            return $locked;
        });
    }
}
