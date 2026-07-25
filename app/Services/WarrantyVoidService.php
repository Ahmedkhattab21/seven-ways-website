<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WarrantyVoided;
use App\Models\Warranty;
use Illuminate\Support\Facades\DB;

class WarrantyVoidService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function void(Warranty $warranty, string $reason): Warranty
    {
        return DB::transaction(function () use ($warranty, $reason) {
            $warranty = Warranty::query()->whereKey($warranty->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                (int) $warranty->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($warranty->branch),
                403
            );
            if ($warranty->status !== 'active') {
                throw new BusinessRuleException('Only active warranties can be voided.');
            }
            $warranty->forceFill([
                'status' => 'void', 'voided_at' => now(), 'voided_by' => $this->tenant->user()->id,
                'void_reason' => $reason,
            ])->save();
            $warranty->items()->where('status', 'active')->update(['status' => 'void']);
            $this->audit->record('warranty.voided', $warranty, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new WarrantyVoided($warranty->id)));

            return $warranty;
        });
    }
}
