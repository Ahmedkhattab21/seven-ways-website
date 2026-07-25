<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QuotationSubmitted
{
    use Dispatchable;

    public function __construct(public int $quotationId)
    {
    }
}
