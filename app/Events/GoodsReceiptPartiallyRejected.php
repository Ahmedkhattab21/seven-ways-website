<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class GoodsReceiptPartiallyRejected
{
    use Dispatchable;

    public function __construct(public int $goodsReceiptId)
    {
    }
}
