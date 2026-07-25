<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequisitionRejected
{
    use Dispatchable;

    public function __construct(public int $requisitionId)
    {
    }
}
