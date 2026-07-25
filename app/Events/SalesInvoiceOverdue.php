<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoiceOverdue
{
    use Dispatchable;

    public function __construct(public int $invoiceId)
    {
    }
}
