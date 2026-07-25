<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CustomerPaymentApproved
{
    use Dispatchable;

    public function __construct(public int $paymentId)
    {
    }
}
