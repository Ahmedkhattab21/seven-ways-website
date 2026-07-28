<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashBoxSession extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'session_number', 'status', 'active_guard',
        'opening_book_balance', 'opening_counted_balance', 'opening_difference',
        'closing_book_balance', 'closing_counted_balance', 'closing_difference',
        'opened_by', 'opened_at', 'counting_started_by', 'counting_started_at',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'closed_by',
        'closed_at', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'business_date' => 'date', 'opening_book_balance' => 'decimal:4',
        'opening_counted_balance' => 'decimal:4', 'opening_difference' => 'decimal:4',
        'closing_book_balance' => 'decimal:4', 'closing_counted_balance' => 'decimal:4',
        'closing_difference' => 'decimal:4', 'opened_at' => 'datetime',
        'counting_started_at' => 'datetime', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashBoxCount::class);
    }
}
