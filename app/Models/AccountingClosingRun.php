<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountingClosingRun extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'run_number', 'status', 'validation_snapshot'];

    protected $casts = [
        'validation_snapshot' => 'array', 'started_at' => 'datetime', 'reviewed_at' => 'datetime',
        'approved_at' => 'datetime', 'completed_at' => 'datetime', 'reopened_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function checklist(): HasOne
    {
        return $this->hasOne(AccountingClosingChecklist::class, 'closing_run_id');
    }
}
