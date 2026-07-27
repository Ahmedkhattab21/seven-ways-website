<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ScheduledReversalCreated;
use App\Events\ScheduledReversalFailed;
use App\Events\ScheduledReversalProcessed;
use App\Models\JournalEntry;
use App\Models\ScheduledJournalReversal;
use Illuminate\Support\Facades\DB;

class ScheduledJournalReversalService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingPeriodResolver $periods,
        private JournalEntryService $journals,
        private AuditService $audit
    ) {
    }

    public function schedule(JournalEntry $entry, string $date): ScheduledJournalReversal
    {
        if ($entry->company_id !== $this->tenant->companyId() || $entry->status !== 'posted'
            || $entry->entry_type !== 'adjustment' || $entry->reversed_by_entry_id) {
            throw new BusinessRuleException('Only an unreversed posted adjustment can be scheduled.');
        }
        $period = $this->periods->resolve($entry->company_id, $date, 'adjustments', $this->tenant->user());
        $key = hash('sha256', implode('|', [$entry->company_id, $entry->id, $date]));
        $scheduled = ScheduledJournalReversal::query()->firstOrCreate(['idempotency_key' => $key], [
            'company_id' => $entry->company_id, 'original_journal_entry_id' => $entry->id,
            'scheduled_date' => $date, 'target_fiscal_year_id' => $period->fiscal_year_id,
            'target_accounting_period_id' => $period->id, 'status' => 'scheduled',
            'created_by' => $this->tenant->user()->id,
        ]);
        if ($scheduled->wasRecentlyCreated) {
            $this->audit->record('scheduled_reversal.created', $scheduled);
            DB::afterCommit(fn () => event(new ScheduledReversalCreated($scheduled->id)));
        }

        return $scheduled;
    }

    public function process(ScheduledJournalReversal $scheduled): ScheduledJournalReversal
    {
        return DB::transaction(function () use ($scheduled) {
            $scheduled = ScheduledJournalReversal::query()->whereKey($scheduled->id)->lockForUpdate()->firstOrFail();
            if ($scheduled->status === 'processed') {
                return $scheduled;
            }
            if (! in_array($scheduled->status, ['scheduled', 'ready', 'failed'], true)) {
                throw new BusinessRuleException('Scheduled reversal cannot be processed.');
            }
            try {
                $reversal = $this->journals->reverse(
                    $scheduled->originalJournal()->with('lines')->firstOrFail(),
                    'Scheduled adjustment reversal',
                    $scheduled->scheduled_date->toDateString()
                );
                $scheduled->forceFill([
                    'status' => 'processed', 'processed_by' => $this->tenant->user()->id,
                    'processed_at' => now(), 'reversal_journal_entry_id' => $reversal->id, 'failure_reason' => null,
                ])->save();
                $this->audit->record('scheduled_reversal.processed', $scheduled);
                DB::afterCommit(fn () => event(new ScheduledReversalProcessed($scheduled->id)));
            } catch (\Throwable $exception) {
                $scheduled->forceFill(['status' => 'failed', 'failure_reason' => $exception->getMessage()])->save();
                DB::afterCommit(fn () => event(new ScheduledReversalFailed($scheduled->id)));
            }

            return $scheduled;
        });
    }

    public function processDue(): int
    {
        $count = 0;
        ScheduledJournalReversal::query()->where('company_id', $this->tenant->companyId())
            ->whereIn('status', ['scheduled', 'ready', 'failed'])->whereDate('scheduled_date', '<=', now())
            ->orderBy('id')->chunkById(100, function ($records) use (&$count) {
                foreach ($records as $record) {
                    $this->process($record);
                    $count++;
                }
            });

        return $count;
    }
}
