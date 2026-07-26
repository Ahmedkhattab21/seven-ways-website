<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

abstract class AccountingDomainEvent
{
    use Dispatchable;

    public function __construct(public int $modelId)
    {
    }
}
