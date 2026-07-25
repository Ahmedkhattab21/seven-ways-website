<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type', 'prefix', 'current_number', 'padding',
        'reset_period', 'period_key', 'scope_key', 'is_active',
    ];

    protected $casts = ['current_number' => 'integer', 'padding' => 'integer', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
