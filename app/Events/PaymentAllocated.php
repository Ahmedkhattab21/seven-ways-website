<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PaymentAllocated
{
    use Dispatchable;

    public function __construct(public int $allocationId)
    {
    }
}
