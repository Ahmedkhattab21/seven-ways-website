<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoiceIssued
{
    use Dispatchable;

    public function __construct(public int $invoiceId)
    {
    }
}
