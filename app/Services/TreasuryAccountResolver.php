<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\PaymentMethodAccountMapping;

class TreasuryAccountResolver
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $bankAccess,
        private CashBoxCustodianService $custodians
    ) {
    }

    public function resolve(
        int $paymentMethodId,
        int $branchId,
        string $operationType,
        int $currencyId,
        ?string $amount = null
    ): array {
        $mapping = PaymentMethodAccountMapping::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('payment_method_id', $paymentMethodId)
            ->whereIn('operation_type', array_unique([$operationType, 'receipt']))
            ->where('is_active', true)->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branchId])
            ->orderByRaw('CASE WHEN operation_type = ? THEN 0 ELSE 1 END', [$operationType])->first();
        if (! $mapping) {
            throw new BusinessRuleException('No active treasury mapping is configured for this operation.');
        }
        $accountId = $mapping->account_id;
        if ($mapping->bank_account_id) {
            $bank = BankAccount::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'active')->findOrFail($mapping->bank_account_id);
            if ($bank->currency_id !== $currencyId) {
                throw new BusinessRuleException('Bank account currency does not match the operation.');
            }
            $ability = in_array($operationType, ['receipt', 'deposit', 'merchant_settlement'], true)
                ? 'can_receive' : ($operationType === 'transfer' ? 'can_transfer' : 'can_pay');
            $this->bankAccess->assert($bank, $branchId, $ability, $amount);
            $accountId = $bank->gl_account_id;
        }
        if ($mapping->cash_box_id) {
            $box = CashBox::query()->where('company_id', $this->tenant->companyId())
                ->where('status', 'active')->findOrFail($mapping->cash_box_id);
            if ($box->currency_id !== $currencyId || $box->branch_id !== $branchId) {
                throw new BusinessRuleException('Cash box currency or branch does not match the operation.');
            }
            $ability = in_array($operationType, ['receipt', 'deposit'], true)
                ? 'can_receive' : ($operationType === 'transfer' ? 'can_transfer' : 'can_pay');
            $this->custodians->assert($box, $ability, $amount);
            $accountId = $box->gl_account_id;
        }
        if (! $accountId) {
            throw new BusinessRuleException('Treasury mapping resolved without a posting account.');
        }

        return [
            'account_id' => (int) $accountId,
            'clearing_account_id' => $mapping->clearing_account_id,
            'fees_account_id' => $mapping->fees_account_id,
            'bank_account_id' => $mapping->bank_account_id,
            'cash_box_id' => $mapping->cash_box_id,
            'mapping_id' => $mapping->id,
        ];
    }
}
