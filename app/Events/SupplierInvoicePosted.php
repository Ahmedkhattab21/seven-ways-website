<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierInvoicePosted
{
    use Dispatchable;

    public function __construct(public int $supplierInvoiceId)
    {
    }
}
