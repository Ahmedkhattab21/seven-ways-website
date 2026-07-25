<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WarrantyClaimSubmitted
{
    use Dispatchable;

    public function __construct(public int $warrantyClaimId)
    {
    }
}
