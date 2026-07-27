<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\JournalEntry;

class ChequePostingService
{
    public function __construct(
        private TreasuryJournalService $journals,
        private BankAccountAccessService $bankAccess
    ) {
    }

    public function recognize(Cheque $cheque): JournalEntry
    {
        if (! $cheque->offset_account_id) {
            throw new BusinessRuleException('Cheque offset account is required for recognition.');
        }
        $dimensions = array_filter([
            'customer_id' => $cheque->customer_id, 'supplier_id' => $cheque->supplier_id,
        ]);
        $lines = $cheque->direction === 'received'
            ? [
                ['account_id' => $cheque->clearing_account_id, 'debit_amount' => $cheque->amount],
                ['account_id' => $cheque->offset_account_id, 'credit_amount' => $cheque->amount] + $dimensions,
            ]
            : [
                ['account_id' => $cheque->offset_account_id, 'debit_amount' => $cheque->amount] + $dimensions,
                ['account_id' => $cheque->clearing_account_id, 'credit_amount' => $cheque->amount],
            ];

        return $this->journals->post(
            $cheque, 'recognize', $cheque->issue_date->toDateString(), $cheque->branch_id,
            $cheque->currency_id, $lines, 'Cheque recognition '.$cheque->document_number
        );
    }

    public function clear(Cheque $cheque, string $date): JournalEntry
    {
        $account = BankAccount::query()->where('company_id', $cheque->company_id)
            ->where('status', 'active')->findOrFail($cheque->bank_account_id);
        if ($account->currency_id !== $cheque->currency_id) {
            throw new BusinessRuleException('Cross-currency cheque clearance is not supported.');
        }
        $this->bankAccess->assert(
            $account, $cheque->branch_id,
            $cheque->direction === 'received' ? 'can_receive' : 'can_pay',
            (string) $cheque->amount
        );
        $lines = $cheque->direction === 'received'
            ? [
                ['account_id' => $account->gl_account_id, 'debit_amount' => $cheque->amount],
                ['account_id' => $cheque->clearing_account_id, 'credit_amount' => $cheque->amount],
            ]
            : [
                ['account_id' => $cheque->clearing_account_id, 'debit_amount' => $cheque->amount],
                ['account_id' => $account->gl_account_id, 'credit_amount' => $cheque->amount],
            ];

        return $this->journals->post(
            $cheque, 'clearance', $date, $cheque->branch_id, $cheque->currency_id,
            $lines, 'Cheque clearance '.$cheque->document_number
        );
    }

    public function bounce(Cheque $cheque, string $reason, string $date): JournalEntry
    {
        return $this->journals->reverse($cheque, 'clearance', $reason, $date);
    }

    public function reverseRecognition(Cheque $cheque, string $reason, ?string $date = null): JournalEntry
    {
        return $this->journals->reverse($cheque, 'recognize', $reason, $date);
    }
}
