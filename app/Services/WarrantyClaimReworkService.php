<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ReworkCreated;
use App\Models\ReworkOrder;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyClaimReworkService
{
    public function __construct(private TenantContext $tenant, private DocumentNumberService $numbers, private AuditService $audit)
    {
    }

    public function create(WarrantyClaim $claim): ReworkOrder
    {
        return DB::transaction(function () use ($claim) {
            $claim = WarrantyClaim::query()->whereKey($claim->id)->lockForUpdate()->with('warranty.workOrder.services')->firstOrFail();
            abort_unless(
                (int) $claim->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($claim->warranty->branch),
                403
            );
            if ($claim->status !== 'approved') {
                throw new BusinessRuleException('Only approved claims can create warranty rework.');
            }
            $existing = ReworkOrder::query()->where('warranty_claim_id', $claim->id)->where('status', '!=', 'cancelled')->first();
            if ($existing) {
                return $existing;
            }
            $order = $claim->warranty->workOrder;
            $rework = new ReworkOrder;
            $rework->forceFill([
                'uuid' => (string) Str::uuid(), 'company_id' => $claim->company_id,
                'branch_id' => $claim->branch_id, 'work_order_id' => $order->id,
                'warranty_claim_id' => $claim->id,
                'rework_number' => $this->numbers->next('rework_order', $claim->company_id, $claim->branch_id),
                'status' => 'approved', 'reason_code' => 'warranty_claim',
                'reason' => $claim->complaint, 'approved_by' => $this->tenant->user()->id,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $coveredServiceIds = $claim->items()->with('warrantyItem')->get()
                ->pluck('warrantyItem.work_order_service_id')->filter()->unique();
            foreach ($order->services->whereIn('id', $coveredServiceIds) as $service) {
                $rework->services()->create([
                    'work_order_service_id' => $service->id, 'reason' => $claim->complaint,
                    'required_action' => 'Warranty corrective work', 'status' => 'pending',
                ]);
            }
            $claim->forceFill(['status' => 'in_rework'])->save();
            $this->audit->record('warranty_claim.rework_created', $rework, ['claim_id' => $claim->id]);
            DB::afterCommit(fn () => event(new ReworkCreated($rework->id, $order->id)));

            return $rework->load('services');
        });
    }
}
