<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankStatementLineIgnored;
use App\Events\BankStatementLineMarkedDuplicate;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\DB;

class BankStatementLineService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function ignore(BankStatementLine $line, string $reason): BankStatementLine
    {
        return DB::transaction(function () use ($line, $reason) {
            $line = $this->lockEditable($line);
            if (bccomp((string) $line->matched_amount, '0', 4) === 1 || blank($reason)) {
                throw new BusinessRuleException('Matched line cannot be ignored and a reason is required.');
            }
            $line->forceFill([
                'status' => 'ignored', 'ignore_reason' => $reason,
                'ignored_by' => $this->tenant->user()->id, 'ignored_at' => now(),
            ])->save();
            $this->audit->record('bank_statement.line_ignored', $line, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new BankStatementLineIgnored($line->id)));

            return $line;
        });
    }

    public function classifyDuplicate(BankStatementLine $line, ?BankStatementLine $duplicateOf, string $reason): BankStatementLine
    {
        return DB::transaction(function () use ($line, $duplicateOf, $reason) {
            $line = $this->lockEditable($line);
            if (bccomp((string) $line->matched_amount, '0', 4) === 1 || blank($reason)) {
                throw new BusinessRuleException('Matched line cannot be reclassified and a reason is required.');
            }
            if ($duplicateOf && ($duplicateOf->bank_account_id !== $line->bank_account_id || $duplicateOf->id === $line->id)) {
                throw new BusinessRuleException('Duplicate source must be another line for the same bank account.');
            }
            $line->forceFill([
                'is_duplicate' => (bool) $duplicateOf, 'duplicate_of_id' => $duplicateOf?->id,
                'status' => $duplicateOf ? 'duplicate' : 'unmatched',
                'ignore_reason' => $reason, 'ignored_by' => $this->tenant->user()->id, 'ignored_at' => now(),
            ])->save();
            $line->statementImport()->update([
                'duplicate_lines' => BankStatementLine::query()
                    ->where('bank_statement_import_id', $line->bank_statement_import_id)
                    ->where('is_duplicate', true)->count(),
            ]);
            $this->audit->record('bank_statement.duplicate_resolved', $line, [
                'duplicate_of_id' => $duplicateOf?->id, 'reason' => $reason,
            ]);
            DB::afterCommit(fn () => event(new BankStatementLineMarkedDuplicate($line->id)));

            return $line;
        });
    }

    private function lockEditable(BankStatementLine $line): BankStatementLine
    {
        $line = BankStatementLine::query()->where('company_id', $this->tenant->companyId())
            ->whereKey($line->id)->lockForUpdate()->firstOrFail();
        if (BankReconciliationSession::query()->where('status', 'completed')
            ->whereHas('imports', fn ($query) => $query->whereKey($line->bank_statement_import_id))->exists()) {
            throw new BusinessRuleException('Line used in completed reconciliation is immutable.');
        }

        return $line;
    }
}
