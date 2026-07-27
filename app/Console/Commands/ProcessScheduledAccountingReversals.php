<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\ScheduledJournalReversal;
use App\Models\User;
use App\Services\ScheduledJournalReversalService;
use Illuminate\Console\Command;

class ProcessScheduledAccountingReversals extends Command
{
    protected $signature = 'accounting:process-scheduled-reversals';

    protected $description = 'Process due scheduled accounting adjustment reversals idempotently';

    public function handle(): int
    {
        $processed = 0;
        $companyIds = ScheduledJournalReversal::query()->whereIn('status', ['scheduled', 'ready', 'failed'])
            ->whereDate('scheduled_date', '<=', now())->distinct()->pluck('company_id');
        foreach ($companyIds as $companyId) {
            $actor = User::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('id')->first();
            if (! $actor || ! Company::query()->whereKey($companyId)->exists()) {
                continue;
            }
            app(TenantContext::class)->initialize($actor);
            $processed += app(ScheduledJournalReversalService::class)->processDue();
        }
        $this->info("Processed {$processed} scheduled reversals.");

        return self::SUCCESS;
    }
}
