<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPayment extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'document_number', 'status', 'idempotency_key',
        'created_by', 'submitted_by', 'approved_by', 'posted_by', 'reversed_by',
        'journal_entry_id', 'reversal_journal_entry_id',
    ];

    protected $casts = [
        'document_date' => 'date', 'exchange_rate' => 'decimal:8', 'amount' => 'decimal:4',
    ];

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
