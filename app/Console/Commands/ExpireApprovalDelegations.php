<?php

namespace App\Console\Commands;

use App\Models\ApprovalDelegation;
use App\Services\SystemNotificationService;
use App\Services\UnifiedAuditService;
use Illuminate\Console\Command;

class ExpireApprovalDelegations extends Command
{
    protected $signature = 'delegations:expire';

    protected $description = 'Expire ended approval delegations and notify their users';

    public function handle(SystemNotificationService $notifications, UnifiedAuditService $audit): int
    {
        ApprovalDelegation::with(['delegator', 'delegate'])->where('status', 'active')->where('ends_at', '<', now())
            ->chunkById(100, function ($items) use ($notifications, $audit) {
                foreach ($items as $delegation) {
                    $delegation->forceFill(['status' => 'expired'])->save();
                    $audit->record('delegation.expired', 'approvals', 'expire', $delegation, [
                        'company_id' => $delegation->company_id, 'branch_id' => $delegation->branch_id,
                    ]);
                    foreach ([$delegation->delegator, $delegation->delegate] as $user) {
                        $notifications->send(
                            $user, 'delegation.expired', 'انتهاء تفويض اعتماد', 'انتهت فترة تفويض الاعتماد.',
                            'delegation-expired:'.$delegation->id.':'.$user->id, $delegation,
                            ['branch_id' => $delegation->branch_id]
                        );
                    }
                }
            });

        return self::SUCCESS;
    }
}
