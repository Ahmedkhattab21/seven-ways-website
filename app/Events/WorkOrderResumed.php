<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderResumed
{
    use Dispatchable;

    public function __construct(public int $workOrderId, public int $serviceId)
    {
    }
}
