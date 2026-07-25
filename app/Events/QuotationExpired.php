<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QuotationExpired
{
    use Dispatchable;

    public function __construct(public int $quotationId)
    {
    }
}
