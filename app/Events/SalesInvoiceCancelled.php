<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoiceCancelled
{
    use Dispatchable;

    public function __construct(public int $invoiceId)
    {
    }
}
