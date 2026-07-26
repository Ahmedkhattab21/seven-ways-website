<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function record(string $event, Model $model, array $metadata = []): void
    {
        $this->recordAs($event, $model, $this->tenant->companyId(), $this->tenant->user()?->id, $metadata);
    }

    public function recordAs(string $event, Model $model, ?int $companyId, ?int $userId, array $metadata = []): void
    {
        $log = new AuditLog(['event' => $event, 'metadata' => $metadata]);
        $log->forceFill([
            'company_id' => $companyId,
            'branch_id' => $this->tenant->companyId() === $companyId ? $this->tenant->branchId() : null,
            'user_id' => $userId,
        ]);
        $log->auditable()->associate($model);
        $log->save();
    }
}
