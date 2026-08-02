<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'sales_invoice_id', 'gross_amount', 'discount_amount', 'net_amount', 'tax_amount', 'total', 'cost_snapshot', 'margin_snapshot', 'issued_movement_id', 'returned_quantity'];

    protected $casts = [
        'metadata' => 'array',
        'warranty_applies' => 'boolean',
        'warranty_snapshot' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function issuedMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'issued_movement_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }
}
