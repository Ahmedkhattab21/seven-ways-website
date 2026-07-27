<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;

class TreasuryTransferPostingService
{
    public function __construct(private TreasuryJournalService $journals)
    {
    }

    public function post(TreasuryTransfer $transfer): JournalEntry
    {
        $source = $this->endpoint($transfer, 'from');
        $destination = $this->endpoint($transfer, 'to');
        if ($source->currency_id !== $destination->currency_id || $source->currency_id !== $transfer->currency_id
            || bccomp((string) $transfer->exchange_rate, '1', 8) !== 0) {
            throw new BusinessRuleException('Cross-currency treasury transfers are not supported.');
        }
        $sourceAccount = $source->gl_account_id;
        $destinationAccount = $destination->gl_account_id;
        $lines = [
            ['account_id' => $destinationAccount, 'branch_id' => $transfer->destination_branch_id ?: $transfer->branch_id,
                'debit_amount' => $transfer->amount],
            ['account_id' => $sourceAccount, 'branch_id' => $transfer->branch_id,
                'credit_amount' => $transfer->amount],
        ];
        if (bccomp((string) $transfer->fees_amount, '0', 4) === 1) {
            $feesAccount = $source instanceof BankAccount ? $source->bank_fees_account_id : null;
            if (! $feesAccount) {
                throw new BusinessRuleException('A source bank fees account is required for transfer fees.');
            }
            $lines[] = ['account_id' => $feesAccount, 'branch_id' => $transfer->branch_id,
                'debit_amount' => $transfer->fees_amount];
            $lines[] = ['account_id' => $sourceAccount, 'branch_id' => $transfer->branch_id,
                'credit_amount' => $transfer->fees_amount];
        }

        return $this->journals->post(
            $transfer, 'post', $transfer->transfer_date->toDateString(), $transfer->branch_id,
            $transfer->currency_id, $lines, 'Treasury transfer '.$transfer->document_number,
            $transfer->reference
        );
    }

    public function reverse(TreasuryTransfer $transfer, string $reason, ?string $date = null): JournalEntry
    {
        return $this->journals->reverse($transfer, 'post', $reason, $date);
    }

    private function endpoint(TreasuryTransfer $transfer, string $side): BankAccount|CashBox
    {
        $type = $transfer->{$side.'_type'};
        $id = $transfer->{$side.'_'.($type === 'bank' ? 'bank_account_id' : 'cash_box_id')};
        $model = $type === 'bank' ? BankAccount::class : CashBox::class;

        return $model::query()->where('company_id', $transfer->company_id)
            ->where('status', 'active')->lockForUpdate()->findOrFail($id);
    }
}
