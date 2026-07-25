<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceMatch extends Model
{
    protected $guarded = ['id', 'supplier_invoice_item_id', 'status', 'price_variance', 'quantity_variance'];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceItem::class, 'supplier_invoice_item_id');
    }
}
