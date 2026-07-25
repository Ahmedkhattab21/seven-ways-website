<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WarrantyVoided
{
    use Dispatchable;

    public function __construct(public int $warrantyId)
    {
    }
}
