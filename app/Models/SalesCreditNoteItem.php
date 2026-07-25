<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCreditNoteItem extends Model
{
    protected $guarded = ['id', 'sales_credit_note_id', 'net_amount', 'tax_amount', 'total'];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SalesCreditNote::class, 'sales_credit_note_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id');
    }
}
