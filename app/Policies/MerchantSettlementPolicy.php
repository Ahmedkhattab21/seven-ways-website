<?php

namespace App\Policies;

use App\Models\MerchantSettlement;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class MerchantSettlementPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, MerchantSettlement $settlement): bool
    {
        return $this->operationScope($user, $settlement, 'treasury.merchant_settlements.view');
    }
}
