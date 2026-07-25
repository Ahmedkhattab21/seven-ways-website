<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QuotationAccepted
{
    use Dispatchable;

    public function __construct(public int $quotationId)
    {
    }
}
