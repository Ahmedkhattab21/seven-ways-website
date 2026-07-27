<?php

namespace App\Services;

use App\Models\BankAdjustment;
use App\Models\JournalEntry;

class BankAdjustmentPostingService
{
    public function __construct(private AccountingPostingService $posting)
    {
    }

    public function post(BankAdjustment $adjustment, ?string $overrideReason = null): JournalEntry
    {
        return $this->posting->post($adjustment, ['override_reason' => $overrideReason]);
    }

    public function reverse(BankAdjustment $adjustment, string $reason, ?string $date = null): JournalEntry
    {
        return $this->posting->reverse($adjustment, $reason, $date);
    }
}
