<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PaymentAllocationReversed
{
    use Dispatchable;

    public function __construct(public int $allocationId)
    {
    }
}
