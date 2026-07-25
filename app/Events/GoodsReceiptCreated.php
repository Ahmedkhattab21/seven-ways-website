<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class GoodsReceiptCreated
{
    use Dispatchable;

    public function __construct(public int $goodsReceiptId)
    {
    }
}
