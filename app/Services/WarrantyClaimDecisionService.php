<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WarrantyClaimApproved;
use App\Events\WarrantyClaimRejected;
use App\Events\WarrantyClaimResolved;
use App\Models\Employee;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;

class WarrantyClaimDecisionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function decide(WarrantyClaim $claim, string $decision, array $items, ?string $reason = null): WarrantyClaim
    {
        return DB::transaction(function () use ($claim, $decision, $items, $reason) {
            $claim = WarrantyClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                (int) $claim->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($claim->warranty->branch),
                403
            );
            if ($claim->status !== 'inspected' || ! in_array($decision, ['covered', 'partially_covered', 'not_covered', 'goodwill'], true)) {
                throw new BusinessRuleException('The claim must be inspected before a coverage decision.');
            }
            if (config('quality.separation_of_duties')
                && $claim->assigned_to
                && Employee::query()->whereKey($claim->assigned_to)->where('user_id', $this->tenant->user()->id)->exists()) {
                throw new BusinessRuleException('The assigned inspector cannot approve their own warranty claim.');
            }
            foreach ($items as $input) {
                $item = $claim->items()->whereKey($input['id'])->lockForUpdate()->firstOrFail();
                $coverage = $decision === 'covered' || $decision === 'goodwill'
                    ? 100
                    : ($decision === 'not_covered' ? 0 : (float) ($input['coverage_percentage'] ?? 0));
                $item->forceFill(['decision' => $decision, 'coverage_percentage' => $coverage])->save();
            }
            $approved = $decision !== 'not_covered';
            $claim->forceFill([
                'status' => $approved ? 'approved' : 'rejected',
                'decision' => $decision,
                'is_free' => in_array($decision, ['covered', 'goodwill'], true),
                'customer_charge_amount' => 0,
                'decision_at' => now(),
                'approved_by' => $this->tenant->user()->id,
                'rejection_reason' => $approved ? null : $reason,
            ])->save();
            $this->audit->record($approved ? 'warranty_claim.approved' : 'warranty_claim.rejected', $claim);
            DB::afterCommit(fn () => event($approved
                ? new WarrantyClaimApproved($claim->id)
                : new WarrantyClaimRejected($claim->id)));

            return $claim->load('items');
        });
    }

    public function resolve(WarrantyClaim $claim): WarrantyClaim
    {
        return DB::transaction(function () use ($claim) {
            $claim = WarrantyClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                (int) $claim->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($claim->warranty->branch),
                403
            );
            $reworks = $claim->reworkOrders()->with('attachments')->lockForUpdate()->get();
            if ($claim->status !== 'under_review'
                || $reworks->isEmpty()
                || $reworks->contains(fn ($rework) => $rework->status !== 'completed')
                || $reworks->contains(fn ($rework) => ! $rework->attachments->contains('category', 'rework_after'))) {
                throw new BusinessRuleException('Completed rework and final quality evidence are required to resolve the claim.');
            }
            $claim->forceFill([
                'status' => 'resolved',
                'resolution_notes' => 'Resolved after completed rework and final quality evidence.',
                'actual_company_cost' => $reworks->sum('total_rework_cost'),
            ])->save();
            $this->audit->record('warranty_claim.resolved', $claim);
            DB::afterCommit(fn () => event(new WarrantyClaimResolved($claim->id)));

            return $claim;
        });
    }
}
