<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QuotationConvertedToAppointment;
use App\Models\Appointment;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class QuotationToAppointmentService
{
    public function __construct(
        private TenantContext $tenant,
        private AppointmentService $appointments,
        private AuditService $audit
    ) {
    }

    public function convert(Quotation $quotation, array $data): Appointment
    {
        return DB::transaction(function () use ($quotation, $data) {
            $locked = Quotation::query()->with(['items.package.items.service'])->lockForUpdate()->findOrFail($quotation->id);
            if ($locked->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($locked->branch)
                || $locked->status !== 'accepted' || $locked->appointments()->whereNotIn('status', ['cancelled'])->exists()) {
                throw new BusinessRuleException('Quotation is not eligible for appointment conversion.');
            }
            $services = [];
            foreach ($locked->items as $item) {
                if ($item->item_type === 'service') {
                    $services[] = [
                        'quotation_item_id' => $item->id, 'service_id' => $item->service_id,
                        'service_package_id' => null, 'description' => $item->description,
                        'quantity' => $item->quantity, 'estimated_duration_minutes' => $item->estimated_duration_minutes ?: 1,
                        'unit_price_snapshot' => $item->unit_price, 'total_snapshot' => $item->total,
                        'assigned_employee_id' => $data['assigned_employee_id'] ?? null,
                    ];
                } elseif ($item->item_type === 'package') {
                    foreach ($item->package->items as $packageItem) {
                        $services[] = [
                            'quotation_item_id' => $item->id, 'service_id' => $packageItem->service_id,
                            'service_package_id' => $item->service_package_id, 'description' => $packageItem->service->name,
                            'quantity' => $packageItem->quantity, 'estimated_duration_minutes' => $packageItem->service->default_duration_minutes,
                            'unit_price_snapshot' => 0, 'total_snapshot' => 0,
                            'assigned_employee_id' => $data['assigned_employee_id'] ?? null,
                        ];
                    }
                }
            }
            if ($services === []) {
                throw new BusinessRuleException('Quotation contains no appointable services.');
            }
            $appointment = $this->appointments->save($data + [
                'branch_id' => $locked->branch_id, 'quotation_id' => $locked->id, 'lead_id' => $locked->lead_id,
                'customer_id' => $locked->customer_id, 'vehicle_id' => $locked->vehicle_id,
                'booking_source' => 'quotation',
            ], $services);
            $locked->forceFill(['status' => 'converted', 'converted_at' => now()])->save();
            $this->audit->record('quotation.converted_to_appointment', $locked, ['appointment_id' => $appointment->id]);
            DB::afterCommit(fn () => event(new QuotationConvertedToAppointment($locked->id, $appointment->id)));

            return $appointment;
        });
    }
}
