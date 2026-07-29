<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AppointmentCheckedIn;
use App\Models\Appointment;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class AppointmentCheckInService
{
    public function __construct(
        private TenantContext $tenant,
        private AuditService $audit,
        private WorkOrderCreationService $workOrders,
        private WorkOrderWarehouseService $workOrderWarehouses,
        private DocumentNumberService $numbers
    ) {
    }

    public function checkIn(Appointment $appointment, array $data): WorkOrder
    {
        return DB::transaction(function () use ($appointment, $data) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($locked->branch)) {
                throw new BusinessRuleException('الحجز خارج نطاق الشركة أو الفروع المسموحة.', status: 403);
            }

            $existing = WorkOrder::query()
                ->where('appointment_id', $locked->id)
                ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
                ->first();
            if ($existing && in_array($locked->status, ['pending', 'confirmed', 'checked_in', 'in_progress'], true)) {
                if ($locked->status === 'checked_in') {
                    $locked->forceFill([
                        'status' => 'in_progress',
                        'updated_by' => $this->tenant->user()?->id,
                    ])->save();
                    $this->audit->record('appointment.work_order_recovered', $locked, [
                        'work_order_id' => $existing->id,
                        'existing_work_order' => true,
                    ]);
                }

                return $existing;
            }

            if (! in_array($locked->status, ['pending', 'confirmed', 'checked_in'], true)) {
                throw new BusinessRuleException('حالة الحجز الحالية لا تسمح ببدء أمر عمل.');
            }

            $isRecovery = $locked->status === 'checked_in';
            $warehouse = $this->workOrderWarehouses->requireDefault($locked->branch);
            $this->numbers->assertConfigured('work_order', $locked->company_id, $locked->branch_id);

            if (! $isRecovery) {
                $locked->forceFill([
                    'status' => 'checked_in',
                    'checked_in_at' => now(),
                    'arrival_notes' => $data['arrival_notes'] ?? null,
                    'odometer_snapshot' => $data['odometer_snapshot'] ?? null,
                    'updated_by' => $this->tenant->user()?->id,
                ])->save();
            }

            $workOrder = $this->workOrders->fromAppointment($locked, $warehouse->id);
            $this->audit->record(
                $isRecovery ? 'appointment.work_order_recovered' : 'appointment.checked_in',
                $locked,
                [
                    'work_order_created' => true,
                    'work_order_id' => $workOrder->id,
                    'warehouse_id' => $warehouse->id,
                ]
            );
            if (! $isRecovery) {
                DB::afterCommit(fn () => event(new AppointmentCheckedIn($locked->id)));
            }

            return $workOrder;
        });
    }
}
