<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementLine;

class BankReconciliationValidationService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingPeriodResolver $periods,
        private BankReconciliationCalculationService $calculation
    ) {
    }

    public function assertCompletable(BankReconciliationSession $session): void
    {
        $session->loadMissing(['bankAccount', 'imports', 'adjustments']);
        if ($session->bankAccount->status !== 'active' || $session->imports->contains('status', '!=', 'imported')) {
            throw new BusinessRuleException('Reconciliation account and imports must remain active and imported.');
        }
        if ($session->imports->contains(fn ($import) => $import->currency_id !== $session->bankAccount->currency_id)) {
            throw new BusinessRuleException('Reconciliation imports and bank account currencies must match.');
        }
        $imports = $session->imports->sortBy('period_start')->values();
        for ($index = 1; $index < $imports->count(); $index++) {
            if ($imports[$index]->period_start <= $imports[$index - 1]->period_end
                || bccomp((string) $imports[$index]->opening_balance, (string) $imports[$index - 1]->closing_balance, 4) !== 0) {
                throw new BusinessRuleException('Statement import periods or opening continuity are invalid.');
            }
        }
        $previous = BankReconciliationSession::query()->where('bank_account_id', $session->bank_account_id)
            ->where('status', 'completed')->whereDate('date_to', '<', $session->date_from)
            ->latest('date_to')->first();
        if ($previous && bccomp(
            $this->absolute(bcsub((string) $session->statement_opening_balance, (string) $previous->statement_closing_balance, 4)),
            (string) $session->tolerance,
            4
        ) === 1) {
            throw new BusinessRuleException('Statement opening does not continue from the previous completed session.');
        }
        $unresolved = BankStatementLine::query()->whereIn('bank_statement_import_id', $session->imports->pluck('id'))
            ->whereIn('status', ['unmatched', 'partially_matched'])->exists();
        if ($unresolved) {
            throw new BusinessRuleException('All statement lines must be matched, ignored, or classified duplicate.');
        }
        if ($session->adjustments->contains(fn ($adjustment) => ! in_array($adjustment->status, ['posted', 'reversed', 'cancelled'], true))) {
            throw new BusinessRuleException('Every reconciliation adjustment must be posted or cancelled.');
        }
        if ($session->matches()->whereIn('status', ['accepted', 'completed'])
            ->whereRaw('ABS(difference_amount) > ?', [$session->tolerance])->exists()) {
            throw new BusinessRuleException('An accepted reconciliation match is outside tolerance.');
        }
        $totals = $this->calculation->calculate($session);
        if (bccomp($this->absolute($totals['difference']), (string) $session->tolerance, 4) === 1) {
            throw new BusinessRuleException('Reconciliation difference is outside tolerance.');
        }
        if (! $session->reviewed_by || ! $session->approved_by
            || in_array($session->approved_by, [$session->started_by, $session->reviewed_by], true)) {
            throw new BusinessRuleException('Reconciliation review and approval separation is incomplete.');
        }
        if ($session->imports->pluck('uploaded_by')->contains($session->approved_by)) {
            throw new BusinessRuleException('Statement importer cannot approve the same reconciliation.');
        }
        $this->periods->resolve(
            $session->company_id, $session->date_to->toDateString(), 'treasury', $this->tenant->user()
        );
        if (BankReconciliationSession::query()->where('bank_account_id', $session->bank_account_id)
            ->where('status', 'completed')->where('id', '!=', $session->id)
            ->whereDate('date_from', '<=', $session->date_to)->whereDate('date_to', '>=', $session->date_from)->exists()) {
            throw new BusinessRuleException('Another completed reconciliation overlaps this session.');
        }
    }

    private function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
