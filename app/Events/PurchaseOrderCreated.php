<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderCreated
{
    use Dispatchable;

    public function __construct(public int $purchaseOrderId)
    {
    }
}
