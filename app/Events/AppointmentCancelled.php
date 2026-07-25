<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AppointmentCancelled
{
    use Dispatchable;

    public function __construct(public int $appointmentId)
    {
    }
}
