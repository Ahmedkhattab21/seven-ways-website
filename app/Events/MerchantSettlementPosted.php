<?php

namespace App\Events;

class MerchantSettlementPosted
{
    public function __construct(public int $settlementId)
    {
    }
}
