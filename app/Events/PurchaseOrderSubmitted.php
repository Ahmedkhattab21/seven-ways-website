<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderSubmitted
{
    use Dispatchable;

    public function __construct(public int $purchaseOrderId)
    {
    }
}
