<?php

namespace App\Events;

class TreasuryTransferCompleted
{
    public function __construct(public int $transferId)
    {
    }
}
