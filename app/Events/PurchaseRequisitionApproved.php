<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequisitionApproved
{
    use Dispatchable;

    public function __construct(public int $requisitionId)
    {
    }
}
