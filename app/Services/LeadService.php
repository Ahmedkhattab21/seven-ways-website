<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        private TenantContext $tenant,
        private PhoneNormalizer $phones,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function save(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            if ($lead->exists) {
                abort_unless((int) $lead->company_id === (int) $this->tenant->companyId(), 403);
            }
            if (! empty($data['vehicle_model_id'])) {
                $validModel = VehicleModel::query()->whereKey($data['vehicle_model_id'])
                    ->when(! empty($data['vehicle_brand_id']), fn ($query) => $query->where('vehicle_brand_id', $data['vehicle_brand_id']))
                    ->exists();
                if (! $validModel) {
                    throw ValidationException::withMessages(['vehicle_model_id' => 'الموديل لا يتبع الماركة المحددة.']);
                }
            }
            if (($data['status'] ?? $lead->status) === 'won' && ! $lead->converted_customer_id) {
                throw ValidationException::withMessages(['status' => 'حالة Won تتم فقط من خلال التحويل إلى عميل.']);
            }
            if (($data['status'] ?? $lead->status) === 'lost' && empty($data['lost_reason'])) {
                throw ValidationException::withMessages(['lost_reason' => 'سبب الخسارة مطلوب.']);
            }
            $branchId = $lead->branch_id ?: $this->tenant->branchId();
            $lead->fill($data)->forceFill([
                'company_id' => $this->tenant->companyId(),
                'branch_id' => $branchId,
                'lead_number' => $lead->lead_number ?: $this->numbers->next('lead', $this->tenant->companyId(), $branchId),
                'normalized_phone' => $this->phones->normalize($data['phone']),
                'vehicle_brand_id' => $data['vehicle_brand_id'] ?? null,
                'vehicle_model_id' => $data['vehicle_model_id'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $lead->created_by ?: $this->tenant->user()?->id,
                'updated_by' => $lead->exists ? $this->tenant->user()?->id : null,
            ])->save();

            return $lead;
        });
    }

    public function markLost(Lead $lead, string $reason): void
    {
        abort_unless((int) $lead->company_id === (int) $this->tenant->companyId(), 403);
        $lead->forceFill(['status' => 'lost', 'lost_reason' => $reason, 'updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('lead.lost', $lead, ['reason' => $reason]);
    }
}
