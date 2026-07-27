<?php

namespace App\Policies;

use App\Models\Cheque;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class ChequePolicy
{
    use TreasuryOperationScope;

    public function view(User $user, Cheque $cheque): bool
    {
        return $this->operationScope($user, $cheque, 'treasury.cheques.view');
    }
}
