<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QualityCheckStarted;
use App\Models\QualityCheck;
use App\Models\QualityChecklistTemplate;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QualityCheckService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function start(WorkOrder $workOrder, ?QualityChecklistTemplate $template = null): QualityCheck
    {
        return DB::transaction(function () use ($workOrder, $template) {
            $workOrder = WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->with('services.service')->firstOrFail();
            $this->assertScoped($workOrder);
            if ($workOrder->status !== 'awaiting_quality') {
                throw new BusinessRuleException('Quality checks can only start for work orders awaiting quality.');
            }
            if (QualityCheck::query()->where('work_order_id', $workOrder->id)->whereIn('status', ['draft', 'in_progress'])->exists()) {
                throw new BusinessRuleException('This work order already has an active quality check.');
            }

            $template = $this->resolveTemplate($workOrder, $template);
            $round = ((int) QualityCheck::query()->where('work_order_id', $workOrder->id)->lockForUpdate()->max('round_number')) + 1;
            $check = new QualityCheck;
            $check->forceFill([
                'uuid' => (string) Str::uuid(),
                'company_id' => $workOrder->company_id,
                'branch_id' => $workOrder->branch_id,
                'work_order_id' => $workOrder->id,
                'quality_check_number' => $this->numbers->next('quality_check', $workOrder->company_id, $workOrder->branch_id),
                'round_number' => $round,
                'status' => 'in_progress',
                'checklist_template_id' => $template->id,
                'template_version' => $template->version,
                'started_at' => now(),
                'checked_by' => $this->tenant->user()->id,
            ])->save();

            $serviceLine = $template->service_id
                ? $workOrder->services->firstWhere('service_id', $template->service_id)
                : null;
            foreach ($template->items as $item) {
                $check->items()->create([
                    'template_item_id' => $item->id,
                    'work_order_service_id' => $serviceLine?->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'category' => $item->category,
                    'check_type' => $item->check_type,
                    'is_required' => $item->is_required,
                    'is_critical' => $item->is_critical,
                    'photo_required' => $item->requires_photo_on_failure,
                    'result' => 'pending',
                ]);
            }
            $this->audit->record('quality.check_started', $check, ['work_order_id' => $workOrder->id, 'round' => $round]);
            DB::afterCommit(fn () => event(new QualityCheckStarted($check->id, $workOrder->id)));

            return $check->load('items');
        });
    }

    public function updateItems(QualityCheck $check, array $items): QualityCheck
    {
        if (! in_array($check->status, ['draft', 'in_progress'], true)) {
            throw new BusinessRuleException('Completed quality checks cannot be edited.');
        }
        $this->assertScoped($check->workOrder);

        return DB::transaction(function () use ($check, $items) {
            foreach ($items as $input) {
                $item = $check->items()->whereKey($input['id'])->lockForUpdate()->firstOrFail();
                if ($input['result'] === 'not_applicable' && $item->is_critical) {
                    if (! $this->tenant->user()->hasPermission('quality_checks.override') || empty($input['not_applicable_reason'])) {
                        throw new BusinessRuleException('Critical checks require override permission and a reason to be not applicable.', status: 403);
                    }
                }
                $item->fill(collect($input)->except('id')->all());
                $item->forceFill(['failed_at' => $input['result'] === 'failed' ? now() : null])->save();
            }

            return $check->load('items');
        });
    }

    private function resolveTemplate(WorkOrder $workOrder, ?QualityChecklistTemplate $requested): QualityChecklistTemplate
    {
        if ($requested) {
            if ((int) $requested->company_id !== (int) $workOrder->company_id || ! $requested->is_active) {
                throw new BusinessRuleException('The selected checklist is unavailable.');
            }

            return $requested->load('items');
        }

        $serviceIds = $workOrder->services->pluck('service_id')->filter();
        $types = $workOrder->services->pluck('service.service_type')->filter();
        $keys = $serviceIds->map(fn ($id) => "service:{$id}")
            ->concat($types->map(fn ($type) => "type:{$type}"))
            ->push('general');
        $template = QualityChecklistTemplate::query()
            ->where('company_id', $workOrder->company_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->whereIn('scope_key', $keys)
            ->orderByRaw("CASE WHEN scope_key LIKE 'service:%' THEN 1 WHEN scope_key LIKE 'type:%' THEN 2 ELSE 3 END")
            ->latest('version')
            ->with('items')
            ->first();
        if (! $template || $template->items->isEmpty()) {
            throw new BusinessRuleException('No active default quality checklist is configured.');
        }

        return $template;
    }

    private function assertScoped(WorkOrder $workOrder): void
    {
        abort_unless(
            (int) $workOrder->company_id === (int) $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($workOrder->branch),
            403
        );
    }
}
