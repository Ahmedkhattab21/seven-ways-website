<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderApproved
{
    use Dispatchable;

    public function __construct(public int $purchaseOrderId)
    {
    }
}
