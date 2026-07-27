<?php

namespace App\Console\Commands;

use App\Models\ApprovalTask;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Console\Command;

class NotifyOverdueApprovals extends Command
{
    protected $signature = 'approvals:mark-overdue';

    protected $description = 'Generate idempotent notifications for overdue approval tasks';

    public function handle(SystemNotificationService $notifications): int
    {
        ApprovalTask::where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<', now())
            ->chunkById(100, function ($tasks) use ($notifications) {
                foreach ($tasks as $task) {
                    User::where('company_id', $task->company_id)
                        ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', $task->required_permission))
                        ->each(fn (User $user) => $notifications->send(
                            $user, 'approval.overdue', 'اعتماد متأخر',
                            "تجاوز المستند {$task->document_number} وقت المراجعة.",
                            'approval-overdue:'.$task->id.':'.$user->id, $task,
                            ['branch_id' => $task->branch_id, 'severity' => 'warning', 'action_url' => route('approvals.show', $task)]
                        ));
                }
            });

        return self::SUCCESS;
    }
}
