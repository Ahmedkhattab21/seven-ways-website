<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierCreditNotePosted
{
    use Dispatchable;

    public function __construct(public int $supplierCreditNoteId)
    {
    }
}
