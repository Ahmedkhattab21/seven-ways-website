<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WarrantyClaimRejected
{
    use Dispatchable;

    public function __construct(public int $warrantyClaimId)
    {
    }
}
