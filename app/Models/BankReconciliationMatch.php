<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliationMatch extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'status', 'matched_amount', 'difference_amount',
        'created_by', 'reviewed_by', 'approved_by',
    ];

    protected $casts = ['matched_amount' => 'decimal:4', 'difference_amount' => 'decimal:4'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationSession::class, 'bank_reconciliation_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationMatchItem::class);
    }
}
