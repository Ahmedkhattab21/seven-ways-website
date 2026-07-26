<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year_id', 'period_number', 'code', 'name', 'start_date', 'end_date',
        'is_adjustment_period', 'locked_modules',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'is_adjustment_period' => 'boolean',
        'locked_modules' => 'array', 'closed_at' => 'datetime', 'reopened_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
