<?php

namespace App\Services;

use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalLimitResolver
{
    public function __construct(private TreasuryApprovalLimitService $treasuryLimits)
    {
    }

    public function assert(User $actor, Model $document, string $action, ?User $delegator = null): void
    {
        if (! $document instanceof TreasuryTransfer) {
            return;
        }

        $this->treasuryLimits->assert(
            $actor, 'transfer', $action, $document->currency_id, (string) $document->amount, $document->branch_id
        );
        if ($delegator) {
            $this->treasuryLimits->assert(
                $delegator, 'transfer', $action, $document->currency_id, (string) $document->amount, $document->branch_id
            );
        }
    }
}
