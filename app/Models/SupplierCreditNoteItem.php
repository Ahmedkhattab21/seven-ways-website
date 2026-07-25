<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditNoteItem extends Model
{
    protected $guarded = ['id', 'supplier_credit_note_id', 'net_amount', 'tax_amount', 'total'];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditNote::class, 'supplier_credit_note_id');
    }
}
