<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderCancelled
{
    use Dispatchable;

    public function __construct(public int $workOrderId)
    {
    }
}
