<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class InspectionCompleted
{
    use Dispatchable;

    public function __construct(public int $inspectionId, public int $workOrderId)
    {
    }
}
