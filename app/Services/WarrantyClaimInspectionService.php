<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;

class WarrantyClaimInspectionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function inspect(WarrantyClaim $claim, array $items, ?string $notes = null): WarrantyClaim
    {
        return DB::transaction(function () use ($claim, $items, $notes) {
            $claim = WarrantyClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $this->assertScoped($claim);
            if (! in_array($claim->status, ['submitted', 'under_review', 'inspection_scheduled'], true)) {
                throw new BusinessRuleException('This claim cannot be inspected in its current state.');
            }
            foreach ($items as $input) {
                $claim->items()->whereKey($input['id'])->lockForUpdate()->firstOrFail()->update([
                    'inspection_result' => $input['inspection_result'],
                    'notes' => $input['notes'] ?? null,
                ]);
            }
            if (! $claim->attachments()->where('category', 'warranty_claim_photo')->exists()) {
                throw new BusinessRuleException('At least one private claim inspection photo is required.');
            }
            $claim->forceFill(['status' => 'inspected', 'inspected_at' => now(), 'resolution_notes' => $notes])->save();
            $this->audit->record('warranty_claim.inspected', $claim);

            return $claim->load('items');
        });
    }

    private function assertScoped(WarrantyClaim $claim): void
    {
        abort_unless(
            (int) $claim->company_id === (int) $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($claim->warranty->branch),
            403
        );
    }
}
