<?php

namespace App\Events;

class TreasuryTransferFailed
{
    public function __construct(public int $transferId)
    {
    }
}
