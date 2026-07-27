<?php

namespace App\Policies;

use App\Models\CashOverShortAdjustment;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class CashOverShortPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, CashOverShortAdjustment $adjustment): bool
    {
        return $this->operationScope($user, $adjustment, 'treasury.cash_over_short.view');
    }
}
