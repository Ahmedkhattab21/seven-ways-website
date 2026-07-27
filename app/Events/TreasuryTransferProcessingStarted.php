<?php

namespace App\Events;

class TreasuryTransferProcessingStarted
{
    public function __construct(public int $transferId)
    {
    }
}
