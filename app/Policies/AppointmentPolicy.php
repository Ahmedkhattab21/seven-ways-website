<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    private function scoped(User $user, Appointment $appointment): bool
    {
        return $user->company_id === $appointment->company_id && $user->canAccessBranch($appointment->branch);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && $user->hasPermission('appointments.view');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && in_array($appointment->status, ['pending', 'confirmed'], true) && $user->hasPermission('appointments.update');
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && $appointment->status === 'pending' && $user->hasPermission('appointments.confirm');
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && $user->hasPermission('appointments.check_in');
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && $user->hasPermission('appointments.cancel');
    }

    public function noShow(User $user, Appointment $appointment): bool
    {
        return $this->scoped($user, $appointment) && $user->hasPermission('appointments.mark_no_show');
    }
}
