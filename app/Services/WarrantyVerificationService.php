<?php

namespace App\Services;

use App\Models\Warranty;
use Illuminate\Support\Str;

class WarrantyVerificationService
{
    public function verify(string $token): array
    {
        $warranty = Warranty::query()
            ->where('qr_token', $token)
            ->with(['company', 'branch', 'vehicle.brand', 'vehicle.model', 'items.service'])
            ->firstOrFail();
        $status = $warranty->status;
        if ($status === 'active' && $warranty->end_date->isPast()) {
            $status = 'expired';
        }
        $plate = $warranty->vehicle->plate_number;
        $vin = $warranty->vehicle->vin;

        return [
            'warranty_number' => $warranty->warranty_number,
            'status' => $status,
            'vehicle' => [
                'make' => $warranty->vehicle->brand?->name_ar ?? $warranty->vehicle->brand?->name_en,
                'model' => $warranty->vehicle->model?->name_ar ?? $warranty->vehicle->model?->name_en,
                'plate' => $plate ? Str::mask($plate, '*', 2, max(strlen($plate) - 4, 1)) : null,
                'vin' => $vin ? '***'.substr($vin, -4) : null,
            ],
            'covered_services' => $warranty->items->map(fn ($item) => [
                'name' => $item->service?->name,
                'start_date' => $item->start_date->toDateString(),
                'end_date' => $item->end_date->toDateString(),
                'status' => $item->status,
            ])->values()->all(),
            'start_date' => $warranty->start_date->toDateString(),
            'end_date' => $warranty->end_date->toDateString(),
            'company' => $warranty->company->name,
            'branch' => $warranty->branch->name,
        ];
    }
}
