<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends BaseModel
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'start_date', 'end_date', 'is_current'];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean',
        'locked_at' => 'datetime', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }
}
