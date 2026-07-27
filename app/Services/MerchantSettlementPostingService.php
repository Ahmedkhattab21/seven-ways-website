<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\MerchantSettlement;
use App\Models\PaymentMethodAccountMapping;

class MerchantSettlementPostingService
{
    public function __construct(
        private TreasuryJournalService $journals,
        private PostingAccountResolver $accounts,
        private BankAccountAccessService $bankAccess
    ) {
    }

    public function post(MerchantSettlement $settlement): JournalEntry
    {
        $bank = BankAccount::query()->where('company_id', $settlement->company_id)
            ->where('status', 'active')->findOrFail($settlement->bank_account_id);
        if ($bank->currency_id !== $settlement->currency_id) {
            throw new BusinessRuleException('Cross-currency merchant settlement is not supported.');
        }
        if ($settlement->branch_id) {
            $this->bankAccess->assert(
                $bank, $settlement->branch_id, 'can_receive', (string) $settlement->net_amount
            );
        }
        $mapping = PaymentMethodAccountMapping::query()->where('company_id', $settlement->company_id)
            ->where('payment_method_id', $settlement->payment_method_id)
            ->where('operation_type', 'receipt')->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $settlement->branch_id)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NULL')->firstOrFail();
        if (! $mapping->clearing_account_id || (bccomp((string) $settlement->fees_amount, '0', 4) === 1
            && ! $mapping->fees_account_id)) {
            throw new BusinessRuleException('Merchant clearing and fees mappings are required.');
        }
        $lines = [
            ['account_id' => $bank->gl_account_id, 'debit_amount' => $settlement->net_amount],
            ['account_id' => $mapping->clearing_account_id, 'credit_amount' => $settlement->gross_amount],
        ];
        if (bccomp((string) $settlement->fees_amount, '0', 4) === 1) {
            $lines[] = ['account_id' => $mapping->fees_account_id, 'debit_amount' => $settlement->fees_amount];
        }
        if (bccomp((string) $settlement->tax_amount, '0', 4) === 1) {
            if (! $settlement->branch_id) {
                throw new BusinessRuleException('Merchant fee VAT requires a branch mapping.');
            }
            $lines[] = [
                'account_id' => $this->accounts->branch(
                    $settlement->company_id, $settlement->branch_id, 'vat_input_account_id'
                ),
                'debit_amount' => $settlement->tax_amount,
            ];
        }

        return $this->journals->post(
            $settlement, 'post', $settlement->settlement_date->toDateString(), $settlement->branch_id,
            $settlement->currency_id, $lines,
            'Merchant settlement '.$settlement->document_number, $settlement->settlement_reference
        );
    }

    public function reverse(MerchantSettlement $settlement, string $reason, ?string $date = null): JournalEntry
    {
        return $this->journals->reverse($settlement, 'post', $reason, $date);
    }
}
