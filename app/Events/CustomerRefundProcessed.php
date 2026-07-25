<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CustomerRefundProcessed
{
    use Dispatchable;

    public function __construct(public int $refundId)
    {
    }
}
