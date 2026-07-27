<?php

namespace App\Events;

class CashBoxSessionOpened
{
    public function __construct(public int $sessionId)
    {
    }
}
