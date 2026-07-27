<?php

namespace App\Policies;

use App\Models\CashPayment;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class CashPaymentPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, CashPayment $payment): bool
    {
        return $this->operationScope($user, $payment, 'treasury.cash_payments.view');
    }
}
