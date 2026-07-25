<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYear extends BaseModel
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'is_current'];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean',
        'locked_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
