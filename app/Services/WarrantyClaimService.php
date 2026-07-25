<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WarrantyClaimSubmitted;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyClaimService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(Warranty $warranty, array $data, array $items): WarrantyClaim
    {
        return DB::transaction(function () use ($warranty, $data, $items) {
            $warranty = Warranty::query()->whereKey($warranty->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless(
                (int) $warranty->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($warranty->branch),
                403
            );
            $expired = $warranty->status === 'expired' || $warranty->end_date->isPast();
            if ($warranty->status !== 'active' && ! $expired) {
                throw new BusinessRuleException('Claims require an active warranty.');
            }
            if ($expired && ! $this->tenant->user()->hasPermission('warranty_claims.approve')) {
                throw new BusinessRuleException('Expired warranty claims require approval permission.', status: 403);
            }
            $itemIds = collect($items)->pluck('warranty_item_id');
            if ($itemIds->isEmpty() || $warranty->items()->whereIn('id', $itemIds)->count() !== $itemIds->unique()->count()) {
                throw new BusinessRuleException('Every claim item must belong to the selected warranty.');
            }
            $claim = new WarrantyClaim;
            $claim->fill(collect($data)->only(['complaint', 'inspection_scheduled_at', 'assigned_to'])->all());
            $claim->forceFill([
                'uuid' => (string) Str::uuid(), 'company_id' => $warranty->company_id,
                'branch_id' => $warranty->branch_id,
                'claim_number' => $this->numbers->next('warranty_claim', $warranty->company_id, $warranty->branch_id),
                'warranty_id' => $warranty->id, 'customer_id' => $warranty->customer_id,
                'vehicle_id' => $warranty->vehicle_id, 'status' => 'submitted',
                'decision' => 'pending', 'reported_at' => now(), 'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($items as $item) {
                $claim->items()->create([
                    'warranty_item_id' => $item['warranty_item_id'],
                    'issue_type' => $item['issue_type'],
                    'description' => $item['description'],
                    'decision' => 'pending',
                    'coverage_percentage' => 0,
                    'estimated_cost' => 0,
                    'actual_cost' => 0,
                ]);
            }
            $this->audit->record('warranty_claim.submitted', $claim);
            DB::afterCommit(fn () => event(new WarrantyClaimSubmitted($claim->id)));

            return $claim->load('items');
        });
    }
}
