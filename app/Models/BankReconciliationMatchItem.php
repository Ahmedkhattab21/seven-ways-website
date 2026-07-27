<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationMatchItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'created_at'];

    protected $casts = ['allocated_amount' => 'decimal:4', 'created_at' => 'datetime'];

    public function reconciliationMatch(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationMatch::class, 'bank_reconciliation_match_id');
    }
}
