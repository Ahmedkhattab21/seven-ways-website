<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSetting extends Model
{
    protected $fillable = [
        'default_tax_id', 'default_payment_method_id', 'default_work_order_warehouse_id',
        'invoice_prefix', 'quotation_prefix',
        'appointment_prefix', 'work_order_prefix', 'purchase_order_prefix',
        'stock_transfer_prefix', 'warranty_prefix', 'maximum_discount_percentage',
        'requires_discount_approval', 'requires_invoice_cancel_approval',
        'allow_negative_stock', 'working_day_start', 'working_day_end', 'weekend_days',
    ];

    protected $casts = [
        'maximum_discount_percentage' => 'decimal:2',
        'requires_discount_approval' => 'boolean',
        'requires_invoice_cancel_approval' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'weekend_days' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function defaultWorkOrderWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_work_order_warehouse_id');
    }
}
