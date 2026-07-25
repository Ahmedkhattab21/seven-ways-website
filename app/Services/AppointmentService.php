<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AppointmentCreated;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AppointmentSchedulingService $scheduling,
        private AuditService $audit
    ) {
    }

    public function save(array $data, array $services, ?Appointment $appointment = null): Appointment
    {
        $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
        $customer = Customer::query()->whereKey($data['customer_id'])->where('company_id', $branch->company_id)->firstOrFail();
        $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])->where('company_id', $branch->company_id)
            ->where('customer_id', $customer->id)->firstOrFail();
        if (! empty($data['quotation_id'])) {
            $quotation = Quotation::query()->whereKey($data['quotation_id'])
                ->where('company_id', $branch->company_id)->where('branch_id', $branch->id)
                ->where('customer_id', $customer->id)->where('vehicle_id', $vehicle->id)
                ->where('status', 'accepted')->firstOrFail();
            if ($quotation->appointments()->whereNotIn('status', ['cancelled'])->when(
                $appointment, fn ($query) => $query->where('id', '!=', $appointment->id)
            )->exists()) {
                throw new BusinessRuleException('Quotation already has an active appointment.');
            }
        }
        if (! empty($data['lead_id'])) {
            Lead::query()->whereKey($data['lead_id'])->where('company_id', $branch->company_id)
                ->where('branch_id', $branch->id)->firstOrFail();
        }
        $employee = ! empty($data['assigned_employee_id'])
            ? Employee::query()->findOrFail($data['assigned_employee_id']) : null;
        $serviceIds = collect($services)->pluck('service_id')->filter()->all();
        $start = Carbon::parse($data['scheduled_start']);
        $end = Carbon::parse($data['scheduled_end']);
        $this->scheduling->validate($branch, $start, $end, $employee, $serviceIds, $appointment);
        collect($services)->filter(fn ($row) => ! empty($row['assigned_employee_id']))
            ->groupBy('assigned_employee_id')->each(function ($rows, $employeeId) use ($branch, $start, $end, $appointment) {
                $assigned = Employee::query()->findOrFail($employeeId);
                $this->scheduling->validate($branch, $start, $end, $assigned, $rows->pluck('service_id')->all(), $appointment);
            });
        if ($appointment?->exists && ! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            throw new BusinessRuleException('This appointment can no longer be edited.');
        }

        return DB::transaction(function () use ($data, $services, $branch, $customer, $vehicle, $start, $end, $appointment) {
            $appointment ??= new Appointment;
            $new = ! $appointment->exists;
            $appointment->fill(collect($data)->except(['branch_id'])->all())->forceFill([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
                'appointment_number' => $appointment->appointment_number ?: $this->numbers->next(
                    'appointment', $branch->company_id, $branch->id, $start
                ),
                'status' => $appointment->status ?: 'pending',
                'estimated_duration_minutes' => $start->diffInMinutes($end),
                'deposit_status' => $data['deposit_required'] ? 'pending' : 'not_required',
                'created_by' => $appointment->created_by ?: $this->tenant->user()?->id,
                'updated_by' => $appointment->exists ? $this->tenant->user()?->id : null,
            ])->save();
            $appointment->services()->delete();
            $appointment->services()->createMany(collect($services)->values()->map(
                fn ($row, $index) => $row + ['sort_order' => $index, 'status' => $row['status'] ?? 'planned']
            )->all());
            $this->audit->record($new ? 'appointment.created' : 'appointment.updated', $appointment);
            if ($new) {
                DB::afterCommit(fn () => event(new AppointmentCreated($appointment->id)));
            }

            return $appointment->fresh(['services']);
        });
    }
}
