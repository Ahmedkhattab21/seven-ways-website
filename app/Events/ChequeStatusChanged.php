<?php

namespace App\Events;

class ChequeStatusChanged
{
    public function __construct(public int $chequeId, public string $status)
    {
    }
}
