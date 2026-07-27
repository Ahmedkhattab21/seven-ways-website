<?php

namespace App\Events;

abstract class TreasuryOperationEvent
{
    public function __construct(public int $operationId)
    {
    }
}
