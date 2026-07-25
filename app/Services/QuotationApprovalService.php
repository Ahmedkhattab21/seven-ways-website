<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QuotationApproved;
use App\Events\QuotationSubmitted;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class QuotationApprovalService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function submit(Quotation $quotation, ?string $notes = null): Quotation
    {
        return $this->transition($quotation, 'draft', 'pending_approval', [
            'submitted_by' => $this->tenant->user()?->id, 'submitted_at' => now(), 'approval_notes' => $notes,
        ], 'quotation.submitted', QuotationSubmitted::class);
    }

    public function approve(Quotation $quotation, ?string $notes = null): Quotation
    {
        if ($quotation->created_by === $this->tenant->user()?->id && ! $this->tenant->user()?->hasRole('company_owner')) {
            throw new BusinessRuleException('The quotation creator cannot approve their own quotation.');
        }

        return $this->transition($quotation, 'pending_approval', 'approved', [
            'approved_by' => $this->tenant->user()?->id, 'approved_at' => now(), 'approval_notes' => $notes,
        ], 'quotation.approved', QuotationApproved::class);
    }

    public function reject(Quotation $quotation, string $reason): Quotation
    {
        return $this->transition($quotation, 'pending_approval', 'rejected', [
            'rejection_reason' => $reason, 'rejected_at' => now(),
        ], 'quotation.approval_rejected', \App\Events\QuotationRejected::class);
    }

    private function transition(Quotation $quotation, string $from, string $to, array $data, string $audit, string $event): Quotation
    {
        return DB::transaction(function () use ($quotation, $from, $to, $data, $audit, $event) {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $this->assertScope($locked);
            if ($locked->status !== $from) {
                throw new BusinessRuleException("Quotation must be {$from}.");
            }
            $locked->forceFill($data + ['status' => $to])->save();
            $this->audit->record($audit, $locked);
            DB::afterCommit(fn () => event(new $event($locked->id)));

            return $locked;
        });
    }

    private function assertScope(Quotation $quotation): void
    {
        if ($quotation->company_id !== $this->tenant->companyId()
            || ! $this->tenant->user()?->canAccessBranch($quotation->branch)) {
            throw new BusinessRuleException('Quotation is outside your scope.', status: 403);
        }
    }
}
