<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderPaused
{
    use Dispatchable;

    public function __construct(public int $workOrderId, public int $serviceId)
    {
    }
}
