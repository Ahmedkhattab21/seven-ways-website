<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WarrantyIssued
{
    use Dispatchable;

    public function __construct(public int $warrantyId, public int $workOrderId)
    {
    }
}
