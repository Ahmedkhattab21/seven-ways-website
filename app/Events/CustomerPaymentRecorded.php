<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CustomerPaymentRecorded
{
    use Dispatchable;

    public function __construct(public int $paymentId)
    {
    }
}
