<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSetting extends Model
{
    protected $fillable = [
        'branch_id', 'invoice_prefix', 'quotation_prefix', 'work_order_prefix',
        'warranty_prefix', 'maximum_discount_percentage', 'requires_discount_approval',
        'requires_invoice_cancel_approval', 'allow_negative_stock',
    ];

    protected $casts = [
        'maximum_discount_percentage' => 'decimal:2',
        'requires_discount_approval' => 'boolean',
        'requires_invoice_cancel_approval' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
