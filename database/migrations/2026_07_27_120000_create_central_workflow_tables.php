<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('module', 50);
            $table->string('document_type', 100);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->dateTime('active_from')->nullable();
            $table->dateTime('active_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'module', 'document_type', 'version'], 'aw_scope_ver_uq');
            $table->index(['company_id', 'is_active'], 'aw_company_active_ix');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->decimal('minimum_amount', 19, 4)->nullable();
            $table->decimal('maximum_amount', 19, 4)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('required_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->string('required_permission', 100);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('minimum_approvals')->default(1);
            $table->boolean('enforce_sod')->default(true);
            $table->timestamps();
            $table->unique(['workflow_id', 'step_order'], 'aws_workflow_order_uq');
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $table->json('modules');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 500);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'delegate_id', 'status'], 'ad_delegate_status_ix');
            $table->index(['delegator_id', 'starts_at', 'ends_at'], 'ad_period_ix');
        });

        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->restrictOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('approval_workflow_steps')->restrictOnDelete();
            $table->nullableMorphs('approvable');
            $table->string('module', 50);
            $table->string('document_type', 100);
            $table->uuid('document_uuid')->nullable();
            $table->string('document_number', 100)->nullable();
            $table->string('stage', 50)->default('approval');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->foreignId('assigned_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('required_permission', 100);
            $table->decimal('amount_snapshot', 19, 4)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->dateTime('due_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->string('decision', 20)->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('delegation_id')->nullable()->constrained('approval_delegations')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status'], 'at_scope_status_ix');
            $table->index(['assigned_user_id', 'status', 'due_at'], 'at_assignee_due_ix');
        });

        Schema::create('approval_task_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_task_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('effective_actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('approval_delegations')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('reason', 500)->nullable();
            $table->string('correlation_id', 64);
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['approval_task_id', 'occurred_at'], 'ata_task_time_ix');
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 80);
            $table->string('severity', 20)->default('info');
            $table->string('title', 200);
            $table->string('message', 500);
            $table->nullableMorphs('related');
            $table->string('action_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at'], 'sn_user_unread_ix');
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('effective_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('delegated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->string('module', 50);
            $table->string('action', 50);
            $table->nullableMorphs('auditable');
            $table->string('document_number', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('correlation_id', 64);
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'module', 'occurred_at'], 'ae_scope_module_time_ix');
            $table->index(['correlation_id'], 'ae_correlation_ix');
        });
    }

    public function down(): void
    {
        // Forward-only migration: central workflow history is intentionally preserved.
    }
};
