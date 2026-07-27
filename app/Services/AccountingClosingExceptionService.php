<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\ClosingExceptionWaived;
use App\Models\AccountingClosingException;
use App\Models\AccountingClosingRun;
use Illuminate\Support\Facades\DB;

class AccountingClosingExceptionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function action(AccountingClosingException $exception, string $action, string $reason): AccountingClosingException
    {
        if ($exception->company_id !== $this->tenant->companyId() || ! in_array($action, ['resolve', 'waive'], true) || blank($reason)) {
            throw new BusinessRuleException('Closing exception action is invalid.');
        }

        return DB::transaction(function () use ($exception, $action, $reason) {
            $exception = AccountingClosingException::query()->whereKey($exception->id)->lockForUpdate()->firstOrFail();
            if ($exception->status !== 'open') {
                return $exception;
            }
            $actor = $this->tenant->user()->id;
            $run = AccountingClosingRun::query()->whereKey($exception->closing_run_id)->lockForUpdate()->firstOrFail();
            if ($action === 'waive' && $run->started_by === $actor) {
                throw new BusinessRuleException('Exception owner cannot waive their own closing exception.');
            }
            $exception->forceFill([
                'status' => $action === 'waive' ? 'waived' : 'resolved',
                'resolution_notes' => $reason,
                $action === 'waive' ? 'waived_by' : 'resolved_by' => $actor,
                $action === 'waive' ? 'waived_at' : 'resolved_at' => now(),
            ])->save();
            if ($action === 'waive') {
                $checklist = $run->checklist()->lockForUpdate()->first();
                $checklist?->items()->where('code', $exception->exception_type)
                    ->where('status', 'failed')->update([
                        'status' => 'waived', 'checked_by' => $actor, 'checked_at' => now(),
                        'result_summary' => 'Waived with independent permission and recorded reason.',
                    ]);
                if ($checklist) {
                    $remaining = $checklist->items()->where('severity', 'blocking')->where('is_required', true)
                        ->whereNotIn('status', ['passed', 'waived', 'not_applicable'])->exists();
                    $checklist->forceFill([
                        'completed_items' => $checklist->items()
                            ->whereIn('status', ['passed', 'warning', 'waived', 'not_applicable'])->count(),
                        'status' => $remaining ? 'blocked' : 'completed',
                        'completed_by' => $remaining ? null : $actor,
                        'completed_at' => $remaining ? null : now(),
                    ])->save();
                }
            }
            $this->audit->record('closing_exception.'.$action, $exception, ['reason' => $reason]);
            if ($action === 'waive') {
                DB::afterCommit(fn () => event(new ClosingExceptionWaived($exception->id)));
            }

            return $exception;
        });
    }
}
