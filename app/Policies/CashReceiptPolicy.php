<?php

namespace App\Policies;

use App\Models\CashReceipt;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class CashReceiptPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, CashReceipt $receipt): bool
    {
        return $this->operationScope($user, $receipt, 'treasury.cash_receipts.view');
    }
}
