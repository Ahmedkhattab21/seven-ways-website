<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierPaymentAllocationReversed
{
    use Dispatchable;

    public function __construct(public int $allocationId)
    {
    }
}
