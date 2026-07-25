<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderReadyToStart
{
    use Dispatchable;

    public function __construct(public int $workOrderId)
    {
    }
}
