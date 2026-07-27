<?php

namespace App\Services;

use App\Models\CashPayment;
use App\Models\CashReceipt;
use App\Models\JournalEntry;

class CashOperationPostingService
{
    public function __construct(private TreasuryJournalService $journals)
    {
    }

    public function post(CashReceipt|CashPayment $operation): JournalEntry
    {
        $cash = $operation->cashBox()->firstOrFail();
        $dimensions = array_filter([
            'customer_id' => $operation->customer_id, 'supplier_id' => $operation->supplier_id,
            'employee_id' => $operation->employee_id,
        ]);
        $lines = $operation instanceof CashReceipt
            ? [
                ['account_id' => $cash->gl_account_id, 'debit_amount' => $operation->amount],
                ['account_id' => $operation->offset_account_id, 'credit_amount' => $operation->amount] + $dimensions,
            ]
            : [
                ['account_id' => $operation->offset_account_id, 'debit_amount' => $operation->amount] + $dimensions,
                ['account_id' => $cash->gl_account_id, 'credit_amount' => $operation->amount],
            ];

        return $this->journals->post(
            $operation, 'post', $operation->document_date->toDateString(), $operation->branch_id,
            $operation->currency_id, $lines, class_basename($operation).' '.$operation->document_number,
            $operation->reference
        );
    }

    public function reverse(CashReceipt|CashPayment $operation, string $reason, ?string $date = null): JournalEntry
    {
        return $this->journals->reverse($operation, 'post', $reason, $date);
    }
}
