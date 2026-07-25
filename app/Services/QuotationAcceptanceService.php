<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QuotationAccepted;
use App\Events\QuotationRejected;
use App\Events\QuotationSent;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class QuotationAcceptanceService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function send(Quotation $quotation): Quotation
    {
        return $this->action($quotation, ['approved'], 'sent', [
            'sent_by' => $this->tenant->user()?->id, 'sent_at' => now(),
        ], 'quotation.sent', QuotationSent::class);
    }

    public function accept(Quotation $quotation, array $data): Quotation
    {
        if ($quotation->valid_until->isBefore(today())) {
            throw new BusinessRuleException('Expired quotation cannot be accepted.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $this->assertScope($locked);
            if (! in_array($locked->status, ['approved', 'sent', 'viewed'], true)) {
                throw new BusinessRuleException('Quotation must be approved before acceptance.');
            }
            $acceptedExists = Quotation::query()->where('company_id', $locked->company_id)
                ->where('quotation_number', $locked->quotation_number)->where('id', '!=', $locked->id)
                ->whereIn('status', ['accepted', 'converted'])->exists();
            if ($acceptedExists) {
                throw new BusinessRuleException('Another version from this quotation family is already accepted.');
            }
            $locked->forceFill([
                'status' => 'accepted', 'accepted_by' => $this->tenant->user()?->id,
                'accepted_at' => now(), 'acceptance_method' => $data['acceptance_method'],
                'accepted_by_name' => $data['accepted_by_name'] ?? null,
                'acceptance_notes' => $data['acceptance_notes'] ?? null,
            ])->save();
            Quotation::query()->where('company_id', $locked->company_id)
                ->where('quotation_number', $locked->quotation_number)->where('id', '!=', $locked->id)
                ->whereNotIn('status', ['converted', 'cancelled', 'rejected', 'expired'])
                ->update(['status' => 'superseded']);
            if ($locked->lead_id) {
                $locked->lead()->update(['status' => 'won']);
            }
            $this->audit->record('quotation.accepted', $locked);
            DB::afterCommit(fn () => event(new QuotationAccepted($locked->id)));

            return $locked;
        });
    }

    public function reject(Quotation $quotation, string $reason): Quotation
    {
        return $this->action($quotation, ['approved', 'sent', 'viewed'], 'rejected', [
            'rejection_reason' => $reason, 'rejected_at' => now(),
        ], 'quotation.rejected', QuotationRejected::class);
    }

    public function cancel(Quotation $quotation, string $reason): Quotation
    {
        return $this->action($quotation, ['draft', 'pending_approval', 'approved', 'sent'], 'cancelled', [
            'rejection_reason' => $reason, 'cancelled_by' => $this->tenant->user()?->id, 'cancelled_at' => now(),
        ], 'quotation.cancelled', null);
    }

    private function action(Quotation $quotation, array $from, string $to, array $data, string $audit, ?string $event): Quotation
    {
        return DB::transaction(function () use ($quotation, $from, $to, $data, $audit, $event) {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $this->assertScope($locked);
            if (! in_array($locked->status, $from, true)) {
                throw new BusinessRuleException('Invalid quotation status transition.');
            }
            $locked->forceFill($data + ['status' => $to])->save();
            $this->audit->record($audit, $locked);
            if ($event) {
                DB::afterCommit(fn () => event(new $event($locked->id)));
            }

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
