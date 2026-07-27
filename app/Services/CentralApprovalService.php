<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalTask;
use App\Models\ApprovalTaskAction;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\CentralApproval\ApprovalModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CentralApprovalService
{
    public function __construct(
        private TenantContext $tenant,
        private ApprovalModuleRegistry $registry,
        private ApprovalLimitResolver $limits,
        private UnifiedAuditService $audit,
        private SystemNotificationService $notifications
    ) {
    }

    public function request(Model $document, string $stage = 'approval'): ApprovalTask
    {
        $handler = $this->registry->for($document::class);
        if ((string) $document->status !== $handler->pendingStatus()) {
            throw new BusinessRuleException('Document is not awaiting approval.');
        }
        $key = implode(':', [$document::class, $document->getKey(), $stage, $document->updated_at?->timestamp ?? 0]);
        $idempotencyKey = hash('sha256', $key);
        $task = ApprovalTask::where('idempotency_key', $idempotencyKey)->first() ?? new ApprovalTask;
        if (! $task->exists) {
            $workflow = ApprovalWorkflow::with('steps')->where('module', $handler->module())
                ->where('document_type', class_basename($document))->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $document->company_id))
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $handler->branchId($document)))
                ->where(fn ($q) => $q->whereNull('active_from')->orWhere('active_from', '<=', now()))
                ->where(fn ($q) => $q->whereNull('active_until')->orWhere('active_until', '>=', now()))
                ->orderByRaw('company_id is null')->orderByRaw('branch_id is null')->orderByDesc('version')->first();
            $step = $workflow?->steps->first(fn ($candidate) => ($candidate->minimum_amount === null
                    || $handler->amount($document) === null
                    || bccomp($handler->amount($document), (string) $candidate->minimum_amount, 4) >= 0)
                && ($candidate->maximum_amount === null
                    || $handler->amount($document) === null
                    || bccomp($handler->amount($document), (string) $candidate->maximum_amount, 4) <= 0));
            $task->forceFill([
                'company_id' => $document->company_id,
                'branch_id' => $handler->branchId($document),
                'workflow_id' => $workflow?->id,
                'workflow_step_id' => $step?->id,
                'approvable_type' => $document::class,
                'approvable_id' => $document->getKey(),
                'module' => $handler->module(),
                'document_type' => class_basename($document),
                'document_uuid' => $document->uuid ?? null,
                'document_number' => $handler->documentNumber($document),
                'stage' => $stage,
                'status' => 'pending',
                'requested_by' => $handler->requesterId($document),
                'requested_at' => now(),
                'assigned_role_id' => $step?->required_role_id,
                'assigned_user_id' => $step?->assigned_user_id,
                'required_permission' => $step?->required_permission ?? $handler->permission(),
                'amount_snapshot' => $handler->amount($document),
                'currency_id' => $handler->currencyId($document),
                'priority' => 'normal',
                'due_at' => now()->addHours(24),
                'metadata' => ['source_status' => $handler->pendingStatus()],
                'idempotency_key' => $idempotencyKey,
            ])->save();
            $this->audit->record('approval.requested', 'approvals', 'request', $task, [
                'company_id' => $task->company_id, 'branch_id' => $task->branch_id,
                'document_number' => $task->document_number,
            ]);
            $this->notifyApprovers($task);
        }

        return $task;
    }

    public function decide(ApprovalTask $task, string $decision, ?string $reason = null): ApprovalTask
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new BusinessRuleException('Unsupported approval decision.');
        }
        if ($decision === 'reject' && blank($reason)) {
            throw new BusinessRuleException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($task, $decision, $reason) {
            $task = ApprovalTask::where('company_id', $this->tenant->companyId())
                ->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($task->status !== 'pending') {
                throw new BusinessRuleException('Approval task has already been completed.');
            }
            $document = $task->approvable_type::whereKey($task->approvable_id)->lockForUpdate()->firstOrFail();
            $handler = $this->registry->for($task->approvable_type);
            abort_unless($document->company_id === $this->tenant->companyId()
                && $document->branch_id === $task->branch_id
                && $this->tenant->user()->canAccessBranch($document->branch), 403);
            if ((string) $document->status !== $handler->pendingStatus()) {
                throw new BusinessRuleException('Source document is no longer awaiting approval.');
            }
            $delegation = $this->authorizeActor($task);
            if ($task->requested_by === $this->tenant->user()->id
                || ($delegation && $task->requested_by === $delegation->delegator_id)) {
                throw new BusinessRuleException('Separation of duties prevents approving the requester document.');
            }
            if ($decision === 'reject' && ! $handler->supportsReject()) {
                throw new BusinessRuleException('This document does not support a central rejection.');
            }
            $delegator = $delegation?->delegator;
            $this->limits->assert($this->tenant->user(), $document, 'approve', $delegator);
            $decision === 'approve' ? $handler->approve($document) : $handler->reject($document, (string) $reason);

            $task->forceFill([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'decision' => $decision, 'decision_reason' => $reason,
                'completed_by' => $this->tenant->user()->id, 'completed_at' => now(),
                'delegation_id' => $delegation?->id,
            ])->save();
            $correlation = (string) (request()?->attributes->get('correlation_id') ?: Str::uuid());
            $action = new ApprovalTaskAction;
            $action->forceFill([
                'approval_task_id' => $task->id, 'actor_id' => $this->tenant->user()->id,
                'effective_actor_id' => $this->tenant->user()->id, 'delegation_id' => $delegation?->id,
                'action' => $decision, 'reason' => $reason, 'correlation_id' => $correlation,
                'occurred_at' => now(),
            ])->save();
            $resultEvent = $decision === 'approve' ? 'approval.approved' : 'approval.rejected';
            $this->audit->record($resultEvent, 'approvals', $decision, $task, [
                'branch_id' => $task->branch_id, 'document_number' => $task->document_number,
                'reason' => $reason, 'delegated_by' => $delegation?->delegator_id,
            ]);
            DB::afterCommit(function () use ($task, $decision) {
                $requester = User::find($task->requested_by);
                if ($requester) {
                    $this->notifications->send(
                        $requester, $decision === 'approve' ? 'approval.approved' : 'approval.rejected', 'تم تحديث طلب الاعتماد',
                        "تم اتخاذ قرار {$decision} للمستند {$task->document_number}.",
                        "approval-result:{$task->id}:{$decision}", $task,
                        ['branch_id' => $task->branch_id, 'action_url' => route('approvals.show', $task)]
                    );
                }
            });

            return $task;
        });
    }

    private function authorizeActor(ApprovalTask $task): ?ApprovalDelegation
    {
        $user = $this->tenant->user();
        abort_unless($user->hasPermission('approvals.act'), 403);
        if ($user->hasPermission($task->required_permission)) {
            return null;
        }
        $delegations = ApprovalDelegation::with('delegator')
            ->where('company_id', $task->company_id)->where('delegate_id', $user->id)
            ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>=', now())
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $task->branch_id))->get();
        $delegation = $delegations->first(fn ($item) => in_array($task->module, $item->modules, true)
            && $item->delegator->hasPermission($task->required_permission));
        abort_unless($delegation, 403);

        return $delegation;
    }

    private function notifyApprovers(ApprovalTask $task): void
    {
        $users = User::where('company_id', $task->company_id)->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', $task->required_permission))->get();
        foreach ($users as $user) {
            $branch = $task->branch_id ? \App\Models\Branch::find($task->branch_id) : null;
            if ($branch && ! $user->canAccessBranch($branch)) {
                continue;
            }
            $this->notifications->send(
                $user, 'approval.requested', 'مهمة اعتماد جديدة',
                "المستند {$task->document_number} ينتظر قرارك.",
                "approval-request:{$task->id}:{$user->id}", $task,
                ['branch_id' => $task->branch_id, 'action_url' => route('approvals.show', $task)]
            );
        }
    }
}
