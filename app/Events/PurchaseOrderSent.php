<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderSent
{
    use Dispatchable;

    public function __construct(public int $purchaseOrderId)
    {
    }
}
