<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PurchaseRequisitionApproved;
use App\Events\PurchaseRequisitionRejected;
use App\Events\PurchaseRequisitionSubmitted;
use App\Models\PurchaseRequisition;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionApprovalService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function submit(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return $this->transition($requisition, 'draft', 'pending_approval', 'submitted', PurchaseRequisitionSubmitted::class);
    }

    public function approve(PurchaseRequisition $requisition, array $quantities = [], ?string $overrideReason = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $quantities, $overrideReason) {
            $requisition = $this->lockScoped($requisition);
            if ($requisition->status !== 'pending_approval') {
                throw new BusinessRuleException('Only pending requisitions can be approved.');
            }
            if (config('purchasing.separation_of_duties', true) && $requisition->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('The requisition creator cannot approve it.');
            }
            foreach ($requisition->items()->lockForUpdate()->get() as $item) {
                $approved = (string) ($quantities[$item->id] ?? $item->requested_quantity);
                if (bccomp($approved, '0', 6) < 0
                    || (bccomp($approved, $item->requested_quantity, 6) === 1 && blank($overrideReason))) {
                    throw new BusinessRuleException('Approved quantity needs a reason when it exceeds requested quantity.');
                }
                $item->forceFill([
                    'approved_quantity' => $approved,
                    'status' => bccomp($approved, '0', 6) === 0 ? 'rejected' : 'approved',
                ])->save();
            }
            $requisition->forceFill([
                'status' => 'approved', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now(),
            ])->save();
            $this->audit->record('purchase_requisition.approved', $requisition, ['override_reason' => $overrideReason]);
            DB::afterCommit(fn () => event(new PurchaseRequisitionApproved($requisition->id)));

            return $requisition;
        });
    }

    public function reject(PurchaseRequisition $requisition, string $reason): PurchaseRequisition
    {
        if (blank($reason)) {
            throw new BusinessRuleException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($requisition, $reason) {
            $requisition = $this->lockScoped($requisition);
            if ($requisition->status !== 'pending_approval') {
                throw new BusinessRuleException('Only pending requisitions can be rejected.');
            }
            $requisition->forceFill([
                'status' => 'rejected', 'rejected_by' => $this->tenant->user()->id,
                'rejected_at' => now(), 'rejection_reason' => $reason,
            ])->save();
            $requisition->items()->where('status', 'pending')->update(['status' => 'rejected']);
            $this->audit->record('purchase_requisition.rejected', $requisition, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new PurchaseRequisitionRejected($requisition->id)));

            return $requisition;
        });
    }

    public function cancel(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition) {
            $requisition = $this->lockScoped($requisition);
            if (! in_array($requisition->status, ['draft', 'pending_approval', 'approved'], true)
                || $requisition->items()->where('ordered_quantity', '>', 0)->exists()) {
                throw new BusinessRuleException('This requisition can no longer be cancelled.');
            }
            $requisition->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()->id, 'cancelled_at' => now(),
            ])->save();
            $requisition->items()->update(['status' => 'cancelled']);

            return $requisition;
        });
    }

    private function transition(PurchaseRequisition $requisition, string $from, string $to, string $action, string $event): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $from, $to, $action, $event) {
            $requisition = $this->lockScoped($requisition);
            if ($requisition->status !== $from || ! $requisition->items()->exists()) {
                throw new BusinessRuleException("Requisition must be {$from} and contain items.");
            }
            $requisition->forceFill([
                'status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now(),
            ])->save();
            $this->audit->record("purchase_requisition.{$action}", $requisition);
            DB::afterCommit(fn () => event(new $event($requisition->id)));

            return $requisition;
        });
    }

    private function lockScoped(PurchaseRequisition $requisition): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();
        abort_unless($requisition->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($requisition->branch), 403);

        return $requisition;
    }
}
