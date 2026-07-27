<?php

namespace App\Console\Commands;

use App\Models\CashBoxSession;
use App\Models\EmployeeAdvance;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Console\Command;

class GenerateOperationalNotifications extends Command
{
    protected $signature = 'notifications:generate-operational';

    protected $description = 'Generate safe idempotent treasury and employee-finance notifications';

    public function handle(SystemNotificationService $notifications): int
    {
        CashBoxSession::whereIn('status', ['open', 'counting'])->where('opened_at', '<', now()->subDay())
            ->chunkById(100, function ($sessions) use ($notifications) {
                foreach ($sessions as $session) {
                    $user = User::find($session->custodian_user_id);
                    if ($user) {
                        $notifications->send($user, 'treasury.cash_session.open', 'جلسة صندوق مفتوحة',
                            "جلسة الصندوق {$session->session_number} مفتوحة لأكثر من يوم.",
                            'cash-session-open:'.$session->id.':'.now()->toDateString(), $session,
                            ['branch_id' => $session->branch_id, 'severity' => 'warning']);
                    }
                }
            });
        EmployeeAdvance::whereIn('status', ['disbursed', 'partially_settled'])
            ->whereColumn('settled_amount', '<', 'amount')->where('request_date', '<', now()->subMonth())
            ->chunkById(100, function ($advances) use ($notifications) {
                foreach ($advances as $advance) {
                    $user = User::find($advance->created_by);
                    if ($user) {
                        $notifications->send($user, 'employee_finance.advance.outstanding', 'سلفة غير مسواة',
                            "السلفة {$advance->advance_number} ما زالت غير مسواة.",
                            'advance-outstanding:'.$advance->id.':'.now()->format('Y-m'), $advance,
                            ['branch_id' => $advance->branch_id, 'severity' => 'warning']);
                    }
                }
            });

        return self::SUCCESS;
    }
}
