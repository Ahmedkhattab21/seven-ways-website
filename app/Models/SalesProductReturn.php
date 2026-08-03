<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesProductReturn extends BaseModel
{
    protected $guarded = ['id', 'company_id', 'created_by'];

    protected $casts = [
        'quantity' => 'decimal:6',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Product return history cannot be deleted.'));
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SalesCreditNote::class, 'sales_credit_note_id');
    }
}
