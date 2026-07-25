<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SupplierCreated
{
    use Dispatchable;

    public function __construct(public int $supplierId)
    {
    }
}
