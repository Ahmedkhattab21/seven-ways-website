<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use App\Models\BankStatementImportProfile;
use Illuminate\Support\Facades\DB;

class BankStatementImportProfileService
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $access,
        private AuditService $audit
    ) {
    }

    public function save(array $data, ?BankStatementImportProfile $profile = null): BankStatementImportProfile
    {
        $companyId = $this->tenant->companyId();
        if (! empty($data['bank_account_id'])) {
            $account = BankAccount::query()->where('company_id', $companyId)->findOrFail($data['bank_account_id']);
            $this->access->assert($account, (int) $this->tenant->branchId(), 'can_view');
        }
        if (($data['thousands_separator'] ?? null) === $data['decimal_separator']) {
            throw new BusinessRuleException('Decimal and thousands separators must differ.');
        }
        $this->assertMapping($data['column_mapping']);

        return DB::transaction(function () use ($data, $profile, $companyId) {
            $creating = ! $profile;
            $profile ??= new BankStatementImportProfile;
            if ($profile->exists && $profile->company_id !== $companyId) {
                throw new BusinessRuleException('Import profile is outside the current company.');
            }
            if ($data['is_default']) {
                BankStatementImportProfile::query()->where('company_id', $companyId)
                    ->where('format', $data['format'])
                    ->where('bank_account_id', $data['bank_account_id'] ?? null)
                    ->when($profile->exists, fn ($query) => $query->whereKeyNot($profile->id))
                    ->update(['is_default' => false, 'default_scope_key' => null]);
            }
            $profile->fill($data);
            $profile->forceFill([
                'company_id' => $companyId,
                'default_scope_key' => $data['is_default']
                    ? implode(':', [$companyId, $data['bank_account_id'] ?? 0, $data['format']]) : null,
                $creating ? 'created_by' : 'updated_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('bank_statement_profile.'.($creating ? 'created' : 'updated'), $profile);

            return $profile;
        });
    }

    private function assertMapping(array $mapping): void
    {
        $allowed = [
            'transaction_date', 'value_date', 'description', 'reference', 'debit', 'credit',
            'amount', 'direction', 'running_balance', 'external_id', 'counterparty_name',
            'counterparty_iban', 'transaction_code',
        ];
        if (array_diff(array_keys($mapping), $allowed)
            || ! isset($mapping['transaction_date'], $mapping['description'])
            || (isset($mapping['debit'], $mapping['credit']) === isset($mapping['amount'], $mapping['direction']))) {
            throw new BusinessRuleException('Import profile column mapping is invalid.');
        }
    }
}
