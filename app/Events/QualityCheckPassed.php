<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QualityCheckPassed
{
    use Dispatchable;

    public function __construct(public int $qualityCheckId, public int $workOrderId)
    {
    }
}
