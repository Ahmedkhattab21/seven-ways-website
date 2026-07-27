<?php

namespace App\Events;

class TreasuryTransferReversed
{
    public function __construct(public int $transferId)
    {
    }
}
