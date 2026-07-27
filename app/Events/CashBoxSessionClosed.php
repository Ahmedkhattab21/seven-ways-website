<?php

namespace App\Events;

class CashBoxSessionClosed
{
    public function __construct(public int $sessionId)
    {
    }
}
