<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankMatchingRuleCreated;
use App\Events\BankMatchingRuleUpdated;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankMatchingRule;
use App\Models\BankStatementLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankMatchingRuleService
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $access,
        private AuditService $audit
    ) {
    }

    public function save(array $data, ?BankMatchingRule $rule = null): BankMatchingRule
    {
        $companyId = $this->tenant->companyId();
        if (! empty($data['bank_account_id'])) {
            $account = BankAccount::query()->where('company_id', $companyId)->findOrFail($data['bank_account_id']);
            $this->access->assert($account, (int) $this->tenant->branchId(), 'can_view');
        }
        if (! empty($data['suggested_account_id'])) {
            Account::query()->where('company_id', $companyId)->where('is_active', true)
                ->where('is_posting', true)->findOrFail($data['suggested_account_id']);
        }
        if ($data['condition_type'] === 'amount_range'
            && (empty($data['amount_min']) || empty($data['amount_max']))) {
            throw new BusinessRuleException('Amount range rule requires minimum and maximum values.');
        }
        if (str_contains((string) ($data['condition_value'] ?? ''), '/')) {
            throw new BusinessRuleException('Raw regular expressions are not supported.');
        }

        return DB::transaction(function () use ($data, $rule, $companyId) {
            $creating = ! $rule;
            $rule ??= new BankMatchingRule;
            if ($rule->exists && $rule->company_id !== $companyId) {
                throw new BusinessRuleException('Matching rule is outside the current company.');
            }
            $rule->fill($data);
            $rule->forceFill([
                'company_id' => $companyId,
                $creating ? 'created_by' : 'updated_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('bank_matching_rule.'.($creating ? 'created' : 'updated'), $rule);
            DB::afterCommit(fn () => event($creating
                ? new BankMatchingRuleCreated($rule->id) : new BankMatchingRuleUpdated($rule->id)));

            return $rule;
        });
    }

    public function disable(BankMatchingRule $rule): BankMatchingRule
    {
        if ($rule->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Matching rule is outside the current company.');
        }
        $rule->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('bank_matching_rule.disabled', $rule);

        return $rule;
    }

    public function applicable(BankStatementLine $line): Collection
    {
        return BankMatchingRule::query()->where('company_id', $line->company_id)->where('is_active', true)
            ->where(fn ($query) => $query->where('bank_account_id', $line->bank_account_id)->orWhereNull('bank_account_id'))
            ->orderByRaw('CASE WHEN bank_account_id IS NULL THEN 1 ELSE 0 END')->orderBy('priority')->limit(100)->get()
            ->filter(fn (BankMatchingRule $rule) => $this->matches($rule, $line))->values();
    }

    private function matches(BankMatchingRule $rule, BankStatementLine $line): bool
    {
        $value = mb_strtolower((string) $rule->condition_value);
        $description = mb_strtolower((string) $line->description);
        $reference = mb_strtolower((string) $line->bank_reference);

        return match ($rule->condition_type) {
            'description_contains' => $value !== '' && str_contains($description, $value),
            'reference_contains' => $value !== '' && str_contains($reference, $value),
            'reference_prefix' => $value !== '' && str_starts_with($reference, $value),
            'reference_exact' => $value !== '' && $reference === $value,
            'transaction_code' => (string) $line->transaction_code === (string) ($rule->transaction_code ?: $rule->condition_value),
            'counterparty_iban_last4' => (string) $line->counterparty_iban_last4 === (string) $rule->condition_value,
            'amount_range' => ($rule->amount_min === null || bccomp($line->amount(), (string) $rule->amount_min, 4) >= 0)
                && ($rule->amount_max === null || bccomp($line->amount(), (string) $rule->amount_max, 4) <= 0),
            default => false,
        };
    }
}
