<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CreditNoteIssued
{
    use Dispatchable;

    public function __construct(public int $creditNoteId)
    {
    }
}
