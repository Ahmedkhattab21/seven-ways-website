<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderAwaitingQuality
{
    use Dispatchable;

    public function __construct(public int $workOrderId)
    {
    }
}
