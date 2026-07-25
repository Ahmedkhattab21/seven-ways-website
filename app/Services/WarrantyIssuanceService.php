<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Events\WarrantyIssued;
use App\Models\Warranty;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyIssuanceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function issueForWorkOrder(WorkOrder $workOrder): ?Warranty
    {
        return DB::transaction(function () use ($workOrder) {
            $workOrder = WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()
                ->with(['services.service', 'services.materials.product', 'services.technicians'])
                ->firstOrFail();
            abort_unless((int) $workOrder->company_id === (int) $this->tenant->companyId(), 403);
            if ($workOrder->status !== 'delivered') {
                return null;
            }
            $existing = Warranty::query()->where('work_order_id', $workOrder->id)->whereIn('status', ['draft', 'active'])->first();
            if ($existing) {
                return $existing;
            }
            $eligible = $workOrder->services->filter(fn ($line) => (int) ($line->service?->default_warranty_months ?? 0) > 0);
            if ($eligible->isEmpty()) {
                return null;
            }
            $start = Carbon::parse($workOrder->delivered_at)->startOfDay();
            $maxMonths = $eligible->max(fn ($line) => (int) $line->service->default_warranty_months);
            $warranty = new Warranty;
            $warranty->forceFill([
                'uuid' => (string) Str::uuid(),
                'company_id' => $workOrder->company_id,
                'branch_id' => $workOrder->branch_id,
                'warranty_number' => $this->numbers->next('warranty', $workOrder->company_id, $workOrder->branch_id),
                'customer_id' => $workOrder->customer_id,
                'vehicle_id' => $workOrder->vehicle_id,
                'work_order_id' => $workOrder->id,
                'status' => 'active',
                'start_date' => $start,
                'end_date' => $start->copy()->addMonthsNoOverflow($maxMonths),
                'terms_snapshot' => config('warranty.default_terms'),
                'qr_token' => Str::random(64),
                'issued_at' => now(),
                'issued_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($eligible as $line) {
                $material = $line->materials->first(fn ($item) => (float) $item->used_quantity > 0);
                $months = max(
                    (int) $line->service->default_warranty_months,
                    (int) ($material?->product?->warranty_months ?? 0)
                );
                $warranty->items()->create([
                    'work_order_service_id' => $line->id,
                    'service_id' => $line->service_id,
                    'product_id' => $material?->product_id,
                    'roll_id' => $material?->roll_id,
                    'technician_id' => $line->technicians->sortByDesc('is_primary')->first()?->employee_id,
                    'warranty_months' => $months,
                    'start_date' => $start,
                    'end_date' => $start->copy()->addMonthsNoOverflow($months),
                    'coverage_terms' => 'Covered when installed and used according to manufacturer guidance.',
                    'exclusions' => 'Accident, misuse, third-party modification, and normal wear.',
                    'status' => 'active',
                ]);
            }
            $this->audit->record('warranty.issued', $warranty, ['work_order_id' => $workOrder->id]);
            DB::afterCommit(fn () => event(new WarrantyIssued($warranty->id, $workOrder->id)));

            return $warranty->load('items');
        });
    }
}
