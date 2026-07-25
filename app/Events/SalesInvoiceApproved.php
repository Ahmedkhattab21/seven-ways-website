<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoiceApproved
{
    use Dispatchable;

    public function __construct(public int $invoiceId)
    {
    }
}
