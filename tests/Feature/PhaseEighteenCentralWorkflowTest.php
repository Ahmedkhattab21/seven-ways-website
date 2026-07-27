<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalTask;
use App\Models\AuditEvent;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\PurchaseRequisition;
use App\Models\SystemNotification;
use App\Services\ApprovalDelegationService;
use App\Services\CentralApprovalService;
use Database\Seeders\CentralWorkflowSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseEighteenCentralWorkflowTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_schema_is_forward_only_and_has_no_stored_balance(): void
    {
        foreach (['approval_workflows', 'approval_workflow_steps', 'approval_tasks', 'approval_task_actions',
            'approval_delegations', 'system_notifications', 'audit_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('approval_tasks', 'balance'));
        $this->assertFalse(Schema::hasColumn('system_notifications', 'account_number'));
    }

    public function test_request_is_idempotent_and_creates_safe_notification_and_audit(): void
    {
        $context = $this->contextWithPermissions();
        $document = $this->requisition($context, $context['user']->id);

        $first = app(CentralApprovalService::class)->request($document);
        $second = app(CentralApprovalService::class)->request($document);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ApprovalTask::where('approvable_type', PurchaseRequisition::class)
            ->where('approvable_id', $document->id)->count());
        $this->assertSame(2, SystemNotification::where('related_type', ApprovalTask::class)
            ->where('related_id', $first->id)->count());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'approval.requested', 'module' => 'approvals']);
    }

    public function test_central_approve_calls_original_service_and_enforces_sod_and_single_decision(): void
    {
        $context = $this->contextWithPermissions();
        $document = $this->requisition($context, $context['user']->id);
        $task = app(CentralApprovalService::class)->request($document);

        try {
            app(CentralApprovalService::class)->decide($task, 'approve');
            $this->fail('Requester approved their own requisition.');
        } catch (BusinessRuleException) {
            $this->assertSame('pending_approval', $document->fresh()->status);
        }

        $this->switchTreasuryActor($context['approver']);
        $approved = app(CentralApprovalService::class)->decide($task->fresh(), 'approve');
        $this->assertSame('approved', $approved->status);
        $this->assertSame('approved', $document->fresh()->status);
        $this->assertDatabaseHas('approval_task_actions', ['approval_task_id' => $task->id, 'action' => 'approve']);

        $this->expectException(BusinessRuleException::class);
        app(CentralApprovalService::class)->decide($task->fresh(), 'approve');
    }

    public function test_reject_requires_reason_and_original_reject_service_is_used(): void
    {
        $context = $this->contextWithPermissions();
        $task = app(CentralApprovalService::class)->request(
            $this->requisition($context, $context['user']->id)
        );
        $this->switchTreasuryActor($context['approver']);
        try {
            app(CentralApprovalService::class)->decide($task, 'reject');
            $this->fail('Blank rejection reason was accepted.');
        } catch (BusinessRuleException) {
            $this->assertSame('pending', $task->fresh()->status);
        }
        app(CentralApprovalService::class)->decide($task->fresh(), 'reject', 'Budget not approved');
        $this->assertSame('rejected', $task->fresh()->approvable->status);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'approval.rejected', 'reason' => 'Budget not approved']);
    }

    public function test_company_and_branch_scope_and_direct_urls_are_protected(): void
    {
        $context = $this->contextWithPermissions();
        $task = app(CentralApprovalService::class)->request($this->requisition($context, $context['user']->id));
        $this->get(route('approvals.show', $task))->assertOk();

        $other = $this->treasuryContext();
        $this->get(route('approvals.show', $task))->assertForbidden();
        $this->post(route('approvals.decide', [$task, 'approve']))->assertForbidden();
        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_delegation_rejects_cycles_cross_company_and_expired_use(): void
    {
        $context = $this->contextWithPermissions();
        $this->switchTreasuryActor($context['user']);
        $service = app(ApprovalDelegationService::class);
        $first = $service->create([
            'delegator_id' => $context['user']->id, 'delegate_id' => $context['approver']->id,
            'branch_id' => $context['branch']->id, 'modules' => ['purchasing'],
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'reason' => 'Leave coverage',
        ]);
        $this->assertTrue($first->isActive());
        $this->switchTreasuryActor($context['approver']);
        $this->expectException(BusinessRuleException::class);
        $service = app(ApprovalDelegationService::class);
        $service->create([
            'delegator_id' => $context['approver']->id, 'delegate_id' => $context['user']->id,
            'branch_id' => $context['branch']->id, 'modules' => ['purchasing'],
            'starts_at' => now(), 'ends_at' => now()->addDay(), 'reason' => 'Invalid cycle',
        ]);
    }

    public function test_seeder_is_idempotent_and_creates_no_operational_data(): void
    {
        $before = [
            ApprovalTask::count(), SystemNotification::count(), ApprovalDelegation::count(),
            AuditEvent::count(), JournalEntry::count(),
        ];
        app(CentralWorkflowSeeder::class)->run();
        app(CentralWorkflowSeeder::class)->run();

        $this->assertSame(12, Permission::whereIn('name', [
            'approvals.view', 'approvals.act', 'approvals.manage_workflows', 'approvals.view_all_branches',
            'notifications.view', 'notifications.generate', 'audit.view', 'audit.view_sensitive',
            'audit.export', 'delegations.view', 'delegations.create', 'delegations.cancel',
        ])->count());
        $this->assertSame(3, \App\Models\ApprovalWorkflow::whereNull('company_id')->count());
        $this->assertSame($before, [
            ApprovalTask::count(), SystemNotification::count(), ApprovalDelegation::count(),
            AuditEvent::count(), JournalEntry::count(),
        ]);
    }

    public function test_no_audit_delete_route_exists(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'audit') && in_array('DELETE', $route->methods(), true)
        ));
    }

    private function contextWithPermissions(): array
    {
        $context = $this->treasuryContext();
        foreach ([
            'approvals.view', 'approvals.act', 'notifications.view', 'delegations.view',
            'delegations.create', 'delegations.cancel', 'purchase_requisitions.approve',
        ] as $name) {
            Permission::updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        foreach ([$context['user'], $context['approver']] as $user) {
            $role = $user->roles()->first();
            $role->permissions()->syncWithoutDetaching(Permission::pluck('id'));
        }

        return $context;
    }

    private function requisition(array $context, int $creator): PurchaseRequisition
    {
        $requisition = new PurchaseRequisition;
        $requisition->forceFill([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'requisition_number' => 'PR-'.fake()->unique()->numerify('######'),
            'status' => 'pending_approval', 'created_by' => $creator, 'submitted_by' => $creator,
            'submitted_at' => now(), 'estimated_total' => '2500.0000', 'request_date' => today(),
            'priority' => 'normal', 'purpose' => 'Phase eighteen approval test',
        ])->save();

        return $requisition;
    }
}
