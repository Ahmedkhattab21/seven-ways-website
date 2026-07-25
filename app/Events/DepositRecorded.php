<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DepositRecorded
{
    use Dispatchable;

    public function __construct(public int $depositId)
    {
    }
}
