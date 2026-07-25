<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data) {
            if (($data['warehouse_type'] ?? null) === 'transit' || ($data['is_system'] ?? false)) {
                throw new BusinessRuleException('System Transit warehouses can only be created by the system.');
            }
            $branchId = (int) ($data['branch_id'] ?? $this->tenant->branchId());
            if (! $this->tenant->accessibleBranches()->contains('id', $branchId)) {
                throw new BusinessRuleException('Branch is outside the current tenant.', status: 403);
            }
            if (($data['is_main'] ?? false) && Warehouse::where('branch_id', $branchId)->where('is_main', true)->lockForUpdate()->exists()) {
                throw new BusinessRuleException('This branch already has a main warehouse.');
            }
            $warehouse = new Warehouse($data);
            $warehouse->forceFill([
                'company_id' => $this->tenant->companyId(), 'branch_id' => $branchId,
                'created_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record('warehouse.created', $warehouse);

            return $warehouse;
        });
    }

    public function disable(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse) {
            $warehouse = Warehouse::lockForUpdate()->findOrFail($warehouse->id);
            if ($warehouse->is_system || $warehouse->warehouse_type === 'transit') {
                throw new BusinessRuleException('System Transit warehouses cannot be disabled manually.');
            }
            if ($warehouse->is_main || $warehouse->balances()->where('quantity', '!=', 0)->exists()
                || $warehouse->rolls()->whereIn('status', ['available', 'opened', 'reserved'])->exists()) {
                throw new BusinessRuleException('Main or non-empty warehouse cannot be disabled.');
            }
            $warehouse->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()?->id])->save();
            $this->audit->record('warehouse.disabled', $warehouse);
        });
    }
}
