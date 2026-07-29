<?php

namespace App\Console\Commands;

use App\Models\CashBoxSession;
use App\Services\AuditService;
use App\Services\UatEnvironmentGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairUatCashSessionClosingWorkflow extends Command
{
    protected $signature = 'uat:repair-cash-session-closing-workflow';

    protected $description = 'Return incorrectly approved UAT cash sessions to counting';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }

        app(UatEnvironmentGuard::class)->assertSafe();
        $repaired = 0;
        DB::transaction(function () use (&$repaired): void {
            CashBoxSession::query()->where('status', 'approved')
                ->whereDoesntHave('counts', fn ($query) => $query
                    ->where('count_type', 'closing')->where('status', 'approved'))
                ->lockForUpdate()->get()->each(function (CashBoxSession $session) use (&$repaired): void {
                    $before = $session->status;
                    $session->forceFill([
                        'status' => 'counting', 'approved_by' => null, 'approved_at' => null,
                        'submitted_by' => null, 'submitted_at' => null,
                    ])->save();
                    app(AuditService::class)->recordAs(
                        'treasury.cash_session.returned_to_counting',
                        $session,
                        $session->company_id,
                        null,
                        ['reason' => 'UAT repair: approved without an approved closing count']
                    );
                    $this->line($session->session_number.' '.$before.' -> '.$session->status);
                    $repaired++;
                });
        });

        $this->info("Repaired {$repaired} session(s) without changing counts, balances, journals, or documents.");

        return self::SUCCESS;
    }
}
