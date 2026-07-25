<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierPaymentAllocated
{
    use Dispatchable;

    public function __construct(public int $allocationId)
    {
    }
}
