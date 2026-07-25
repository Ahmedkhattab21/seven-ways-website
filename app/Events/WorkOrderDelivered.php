<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderDelivered
{
    use Dispatchable;

    public function __construct(public int $workOrderId)
    {
    }
}
