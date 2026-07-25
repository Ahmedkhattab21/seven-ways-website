<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Support\Facades\DB;

class LeadFollowUpService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function create(Lead $lead, array $data): LeadFollowUp
    {
        return DB::transaction(function () use ($lead, $data) {
            abort_unless(
                (int) $lead->company_id === (int) $this->tenant->companyId()
                && $this->tenant->accessibleBranches()->contains('id', $lead->branch_id),
                403
            );
            $followUp = new LeadFollowUp($data);
            $followUp->forceFill([
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $this->tenant->user()?->id,
            ]);
            $lead->followUps()->save($followUp);
            $lead->forceFill([
                'next_follow_up_at' => $data['next_follow_up_at'] ?? $data['scheduled_at'] ?? null,
            ])->save();

            return $followUp;
        });
    }
}
