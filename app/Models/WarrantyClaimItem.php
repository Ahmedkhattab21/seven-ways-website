<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimItem extends Model
{
    protected $guarded = ['id', 'warranty_claim_id'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'warranty_claim_id');
    }

    public function warrantyItem(): BelongsTo
    {
        return $this->belongsTo(WarrantyItem::class);
    }
}
