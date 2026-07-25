<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function __construct(private TenantContext $tenant, private PlateNormalizer $plates)
    {
    }

    public function save(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data) {
            if ($vehicle->exists) {
                abort_unless((int) $vehicle->company_id === (int) $this->tenant->companyId(), 403);
            }
            $customer = Customer::query()->where('company_id', $this->tenant->companyId())
                ->findOrFail($data['customer_id']);
            $model = VehicleModel::query()->where('vehicle_brand_id', $data['vehicle_brand_id'])
                ->findOrFail($data['vehicle_model_id']);
            $this->assertCompanyReference(VehicleType::class, $data['vehicle_type_id'] ?? null, 'vehicle_type_id');
            $this->assertCompanyReference(VehicleSize::class, $data['vehicle_size_id'] ?? null, 'vehicle_size_id');
            $year = $data['manufacturing_year'] ?? null;
            if ($year && (($model->start_year && $year < $model->start_year) || ($model->end_year && $year > $model->end_year))) {
                throw ValidationException::withMessages(['manufacturing_year' => 'سنة الصنع خارج نطاق الموديل.']);
            }
            $normalizedPlate = $this->plates->normalize($data['plate_number'] ?? null);
            $duplicate = Vehicle::withTrashed()->where('company_id', $this->tenant->companyId())
                ->when($normalizedPlate, fn ($query) => $query->where('normalized_plate_number', $normalizedPlate))
                ->when($vehicle->exists, fn ($query) => $query->whereKeyNot($vehicle->id))
                ->exists();
            if ($normalizedPlate && $duplicate) {
                throw ValidationException::withMessages(['plate_number' => 'رقم اللوحة مستخدم داخل الشركة.']);
            }
            $vehicle->fill($data)->forceFill([
                'company_id' => $this->tenant->companyId(),
                'customer_id' => $customer->id,
                'created_branch_id' => $vehicle->created_branch_id ?: $this->tenant->branchId(),
                'vehicle_brand_id' => $data['vehicle_brand_id'],
                'vehicle_model_id' => $model->id,
                'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
                'vehicle_size_id' => $data['vehicle_size_id'] ?? null,
                'normalized_plate_number' => $normalizedPlate,
                'vin' => isset($data['vin']) ? strtoupper(trim($data['vin'])) : null,
                'created_by' => $vehicle->created_by ?: $this->tenant->user()?->id,
                'updated_by' => $vehicle->exists ? $this->tenant->user()?->id : null,
            ])->save();

            return $vehicle;
        });
    }

    private function assertCompanyReference(string $model, ?int $id, string $field): void
    {
        if (! $id) {
            return;
        }

        $valid = $model::query()->whereKey($id)
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $this->tenant->companyId()))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([$field => 'القيمة المرجعية خارج نطاق الشركة.']);
        }
    }
}
