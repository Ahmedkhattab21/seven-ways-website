<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OperationalDepositConverted
{
    use Dispatchable;

    public function __construct(public int $depositId, public int $paymentId)
    {
    }
}
