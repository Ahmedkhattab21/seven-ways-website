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
        $log = new AuditLog(['event' => $event, 'metadata' => $metadata]);
        $log->forceFill([
            'company_id' => $this->tenant->companyId(),
            'branch_id' => $this->tenant->branchId(),
            'user_id' => $this->tenant->user()?->id,
        ]);
        $log->auditable()->associate($model);
        $log->save();
    }
}
