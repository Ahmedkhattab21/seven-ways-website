<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierInvoiceOverdue
{
    use Dispatchable;

    public function __construct(public int $supplierInvoiceId)
    {
    }
}
