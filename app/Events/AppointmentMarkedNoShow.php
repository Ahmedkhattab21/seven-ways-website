<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AppointmentMarkedNoShow
{
    use Dispatchable;

    public function __construct(public int $appointmentId)
    {
    }
}
