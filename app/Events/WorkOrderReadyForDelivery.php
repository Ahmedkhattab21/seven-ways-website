<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderReadyForDelivery
{
    use Dispatchable;

    public function __construct(public int $workOrderId, public int $qualityCheckId)
    {
    }
}
