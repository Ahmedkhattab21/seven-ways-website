<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseReturnPosted
{
    use Dispatchable;

    public function __construct(public int $purchaseReturnId)
    {
    }
}
