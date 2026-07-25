<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\StockMovement;
use Illuminate\Support\Str;

class StockMovementService
{
    public function __construct(private TenantContext $tenant, private DocumentNumberService $numbers)
    {
    }

    public function record(array $data): StockMovement
    {
        $allowedReferenceTypes = array_merge(
            [null, 'stock_opening', 'stock_adjustment', 'inventory_count', 'roll', 'roll_scrap', 'reservation', 'reversal'],
            config('inventory.reservation_reference_types', [])
        );
        if (! in_array($data['reference_type'] ?? null, $allowedReferenceTypes, true)) {
            throw new BusinessRuleException('Unsupported inventory reference type.');
        }
        $movement = new StockMovement;
        $movement->forceFill($data + [
            'uuid' => (string) Str::uuid(),
            'movement_number' => $this->numbers->next('stock_movement', $this->tenant->companyId(), $data['branch_id']),
            'created_by' => $this->tenant->user()->id,
            'occurred_at' => now(),
        ]);
        $movement->save();

        return $movement;
    }
}
