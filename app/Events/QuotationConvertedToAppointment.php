<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QuotationConvertedToAppointment
{
    use Dispatchable;

    public function __construct(public int $quotationId, public int $appointmentId)
    {
    }
}
