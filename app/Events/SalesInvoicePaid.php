<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoicePaid
{
    use Dispatchable;

    public function __construct(public int $invoiceId)
    {
    }
}
