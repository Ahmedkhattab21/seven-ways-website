<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ReworkStarted
{
    use Dispatchable;

    public function __construct(public int $reworkOrderId, public int $workOrderId)
    {
    }
}
