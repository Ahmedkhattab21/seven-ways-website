<?php

namespace App\Analytics;

use App\Core\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

class ReportFilterData
{
    public function __construct(
        public readonly int $companyId,
        public readonly array $branchIds,
        public readonly string $dateFrom,
        public readonly string $dateTo,
        public readonly ?int $currencyId = null,
        public readonly ?int $customerId = null,
        public readonly ?int $supplierId = null,
        public readonly ?int $employeeId = null,
        public readonly ?int $productId = null,
        public readonly ?int $warehouseId = null,
        public readonly ?string $status = null,
        public readonly int $movementDays = 90,
        public readonly bool $includeCompanyWide = false
    ) {
    }

    public static function from(array $validated, TenantContext $tenant): self
    {
        $accessible = $tenant->accessibleBranches()->pluck('id')->map(fn ($id) => (int) $id)->values();
        $requested = collect($validated['branch_ids'] ?? [])
            ->when($validated['branch_id'] ?? null, fn ($ids) => $ids->push((int) $validated['branch_id']))
            ->map(fn ($id) => (int) $id)->unique()->values();

        if ($requested->diff($accessible)->isNotEmpty()) {
            throw new AuthorizationException('Report branch is outside the accessible scope.');
        }

        $canSeeAll = $tenant->user()->hasRole('system_admin')
            || $tenant->user()->hasPermission('reports.view_all_branches');
        if ($requested->isEmpty()) {
            $requested = $canSeeAll
                ? $accessible
                : collect(array_filter([$tenant->branchId()]))->map(fn ($id) => (int) $id);
        }

        $to = CarbonImmutable::parse($validated['date_to'] ?? now()->toDateString())->startOfDay();
        $from = CarbonImmutable::parse($validated['date_from'] ?? $to->startOfMonth()->toDateString())->startOfDay();

        return new self(
            (int) $tenant->companyId(),
            $requested->all(),
            $from->toDateString(),
            $to->toDateString(),
            isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
            isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            isset($validated['employee_id']) ? (int) $validated['employee_id'] : null,
            isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            $validated['status'] ?? null,
            (int) ($validated['movement_days'] ?? 90),
            $canSeeAll && $requested->sort()->values()->all() === $accessible->sort()->values()->all()
        );
    }

    public function previousPeriod(): self
    {
        $from = CarbonImmutable::parse($this->dateFrom);
        $to = CarbonImmutable::parse($this->dateTo);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();

        return new self(
            $this->companyId,
            $this->branchIds,
            $previousTo->subDays($days - 1)->toDateString(),
            $previousTo->toDateString(),
            $this->currencyId,
            $this->customerId,
            $this->supplierId,
            $this->employeeId,
            $this->productId,
            $this->warehouseId,
            $this->status,
            $this->movementDays,
            $this->includeCompanyWide
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
