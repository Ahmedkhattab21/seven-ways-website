<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class WorkOrderWarehouseService
{
    public function eligibleQuery(Branch $branch): Builder
    {
        return Warehouse::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('is_system', false)
            ->where('allows_work_order_issue', true);
    }

    public function defaultFor(Branch $branch): ?Warehouse
    {
        $warehouseId = $branch->settings?->default_work_order_warehouse_id;

        return $warehouseId
            ? $this->eligibleQuery($branch)->whereKey($warehouseId)->first()
            : null;
    }

    public function requireDefault(Branch $branch): Warehouse
    {
        $warehouseId = $branch->settings?->default_work_order_warehouse_id;
        if (! $warehouseId) {
            throw new BusinessRuleException(
                'لا يوجد مستودع افتراضي لصرف خامات أوامر العمل في هذا الفرع. يرجى تحديده من إعدادات الفرع أولًا.'
            );
        }

        return $this->defaultFor($branch) ?? throw new BusinessRuleException(
            'مستودع أوامر العمل الافتراضي غير نشط أو غير مسموح له بصرف خامات أوامر العمل.'
        );
    }

    public function assertEligibleSelection(Branch $branch, ?int $warehouseId): void
    {
        if ($warehouseId && ! $this->eligibleQuery($branch)->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'default_work_order_warehouse_id' => 'المستودع المختار غير صالح لأوامر العمل أو لا يتبع الفرع الحالي.',
            ]);
        }
    }
}
