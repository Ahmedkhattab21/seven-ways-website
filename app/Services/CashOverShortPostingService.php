<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashOverShortAdjustment;
use App\Models\JournalEntry;

class CashOverShortPostingService
{
    public function __construct(private TreasuryJournalService $journals)
    {
    }

    public function post(CashOverShortAdjustment $adjustment): JournalEntry
    {
        $session = $adjustment->session()->with('cashBox')->firstOrFail();
        $box = $session->cashBox;
        if (! $box->over_short_account_id) {
            throw new BusinessRuleException('حساب فروق العجز والزيادة غير مربوط بالخزينة. يرجى ضبطه من إعدادات الخزائن قبل الترحيل.');
        }
        $lines = $adjustment->adjustment_type === 'cash_over'
            ? [
                ['account_id' => $box->gl_account_id, 'debit_amount' => $adjustment->amount],
                ['account_id' => $box->over_short_account_id, 'credit_amount' => $adjustment->amount],
            ]
            : [
                ['account_id' => $box->over_short_account_id, 'debit_amount' => $adjustment->amount],
                ['account_id' => $box->gl_account_id, 'credit_amount' => $adjustment->amount],
            ];

        return $this->journals->post(
            $adjustment, 'post', $session->business_date->toDateString(), $session->branch_id,
            $box->currency_id, $lines, 'Cash '.$adjustment->adjustment_type
        );
    }

    public function reverse(CashOverShortAdjustment $adjustment, string $reason, ?string $date = null): JournalEntry
    {
        return $this->journals->reverse($adjustment, 'post', $reason, $date);
    }
}
