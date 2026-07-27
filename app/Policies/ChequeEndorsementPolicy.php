<?php

namespace App\Policies;

use App\Models\ChequeEndorsement;
use App\Models\User;

class ChequeEndorsementPolicy
{
    public function approve(User $user, ChequeEndorsement $endorsement): bool
    {
        return $endorsement->company_id === $user->company_id
            && $endorsement->created_by !== $user->id
            && $user->hasPermission('treasury.cheques.endorse');
    }
}
