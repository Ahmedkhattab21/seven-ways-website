<?php

namespace App\Policies;

use App\Models\AppointmentDeposit;
use App\Models\User;

class AppointmentDepositPolicy
{
    public function view(User $user, AppointmentDeposit $deposit): bool
    {
        return $user->company_id === $deposit->company_id && $user->canAccessBranch($deposit->appointment->branch)
            && $user->hasPermission('appointment_deposits.view');
    }

    public function cancel(User $user, AppointmentDeposit $deposit): bool
    {
        return $this->view($user, $deposit) && $deposit->status === 'recorded'
            && $user->hasPermission('appointment_deposits.cancel');
    }
}
