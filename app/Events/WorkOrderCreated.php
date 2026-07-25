<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderCreated
{
    use Dispatchable;

    public function __construct(public int $workOrderId)
    {
    }
}
