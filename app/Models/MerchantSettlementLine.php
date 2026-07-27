<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSettlementLine extends Model
{
    protected $guarded = ['id', 'gross_amount', 'allocated_amount', 'fees_share', 'net_amount'];

    protected $casts = [
        'gross_amount' => 'decimal:4', 'allocated_amount' => 'decimal:4',
        'fees_share' => 'decimal:4', 'net_amount' => 'decimal:4',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(MerchantSettlement::class, 'merchant_settlement_id');
    }
}
