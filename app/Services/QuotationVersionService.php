<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class QuotationVersionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function create(Quotation $quotation, string $reason): Quotation
    {
        return DB::transaction(function () use ($quotation, $reason) {
            $current = Quotation::query()->with('items.materials')->lockForUpdate()->findOrFail($quotation->id);
            if ($current->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($current->branch)) {
                throw new BusinessRuleException('Quotation is outside your scope.', status: 403);
            }
            if (in_array($current->status, ['draft', 'converted'], true)) {
                throw new BusinessRuleException('This quotation status cannot create a new version.');
            }
            $latest = Quotation::query()->where('company_id', $current->company_id)
                ->where('quotation_number', $current->quotation_number)->lockForUpdate()->max('version_number');
            $copy = $current->replicate([
                'uuid', 'status', 'version_number', 'submitted_by', 'approved_by', 'sent_by', 'accepted_by',
                'cancelled_by', 'submitted_at', 'approved_at', 'sent_at', 'accepted_at', 'rejected_at',
                'cancelled_at', 'converted_at', 'acceptance_method', 'accepted_by_name', 'acceptance_notes',
            ]);
            $copy->forceFill([
                'version_number' => $latest + 1, 'parent_quotation_id' => $current->parent_quotation_id ?: $current->id,
                'status' => 'draft', 'created_by' => $this->tenant->user()?->id, 'internal_notes' => trim(
                    ($current->internal_notes ? $current->internal_notes."\n" : '').'Version reason: '.$reason
                ),
            ])->save();
            foreach ($current->items as $oldItem) {
                $newItem = $copy->items()->create($oldItem->replicate()->toArray());
                foreach ($oldItem->materials as $material) {
                    $newItem->materials()->create($material->replicate()->toArray());
                }
            }
            $current->forceFill(['status' => 'superseded'])->save();
            $this->audit->record('quotation.version_created', $copy, ['previous_id' => $current->id]);

            return $copy->fresh(['items.materials']);
        });
    }
}
