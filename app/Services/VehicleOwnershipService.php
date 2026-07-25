<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleOwnershipHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleOwnershipService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function transfer(Vehicle $vehicle, int $customerId, array $data = []): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $customerId, $data) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            abort_unless((int) $vehicle->company_id === (int) $this->tenant->companyId(), 403);
            $customer = Customer::query()->where('company_id', $this->tenant->companyId())->findOrFail($customerId);
            if ((int) $vehicle->customer_id === (int) $customer->id) {
                throw ValidationException::withMessages(['customer_id' => 'السيارة مملوكة بالفعل لهذا العميل.']);
            }
            $history = new VehicleOwnershipHistory([
                'transferred_at' => $data['transferred_at'] ?? now(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $history->forceFill([
                'vehicle_id' => $vehicle->id, 'from_customer_id' => $vehicle->customer_id,
                'to_customer_id' => $customer->id, 'created_by' => $this->tenant->user()?->id,
            ])->save();
            $from = $vehicle->customer_id;
            $vehicle->forceFill(['customer_id' => $customer->id, 'updated_by' => $this->tenant->user()?->id])->save();
            $this->audit->record('vehicle.ownership_transferred', $vehicle, [
                'from_customer_id' => $from, 'to_customer_id' => $customer->id,
            ]);

            return $vehicle;
        });
    }
}
