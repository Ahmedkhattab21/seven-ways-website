<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\QualityChecklistTemplate;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QualityChecklistService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function createVersion(array $data, array $items): QualityChecklistTemplate
    {
        return DB::transaction(function () use ($data, $items) {
            $scopeKey = $this->scopeKey($data['service_id'] ?? null, $data['service_type'] ?? null);
            if (! empty($data['service_id'])) {
                $validService = Service::query()
                    ->whereKey($data['service_id'])
                    ->where('company_id', $this->tenant->companyId())
                    ->exists();
                if (! $validService) {
                    throw new BusinessRuleException('The checklist service is outside the current company.');
                }
            }

            $versions = QualityChecklistTemplate::withTrashed()
                ->where('company_id', $this->tenant->companyId())
                ->where('scope_key', $scopeKey)
                ->lockForUpdate();
            $version = ((int) $versions->max('version')) + 1;
            if (! empty($data['is_default'])) {
                QualityChecklistTemplate::query()
                    ->where('company_id', $this->tenant->companyId())
                    ->where('scope_key', $scopeKey)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template = new QualityChecklistTemplate;
            $template->fill(collect($data)->except(['items', 'version'])->all());
            $template->forceFill([
                'uuid' => (string) Str::uuid(),
                'company_id' => $this->tenant->companyId(),
                'scope_key' => $scopeKey,
                'version' => $version,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($items as $position => $item) {
                $template->items()->create(array_merge($item, ['sort_order' => $item['sort_order'] ?? $position]));
            }
            $this->audit->record('quality.template_version_created', $template, ['version' => $version]);

            return $template->load('items');
        });
    }

    public function setActive(QualityChecklistTemplate $template, bool $active): QualityChecklistTemplate
    {
        if ((int) $template->company_id !== (int) $this->tenant->companyId()) {
            abort(403);
        }
        if (! $active && $template->is_default) {
            throw new BusinessRuleException('Set another default checklist before deactivating this one.');
        }
        $template->forceFill(['is_active' => $active])->save();

        return $template;
    }

    public function scopeKey(?int $serviceId, ?string $serviceType): string
    {
        return $serviceId ? "service:{$serviceId}" : ($serviceType ? "type:{$serviceType}" : 'general');
    }
}
