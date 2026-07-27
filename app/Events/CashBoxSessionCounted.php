<?php

namespace App\Events;

class CashBoxSessionCounted
{
    public function __construct(public int $sessionId)
    {
    }
}
