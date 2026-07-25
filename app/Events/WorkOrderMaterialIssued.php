<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkOrderMaterialIssued
{
    use Dispatchable;

    public function __construct(public int $workOrderId, public int $materialId)
    {
    }
}
