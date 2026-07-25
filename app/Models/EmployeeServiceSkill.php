<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeServiceSkill extends Model
{
    protected $fillable = ['skill_level', 'is_primary', 'is_active', 'certified_at', 'certification_expires_at', 'notes'];

    protected $casts = [
        'is_primary' => 'boolean', 'is_active' => 'boolean', 'certified_at' => 'date',
        'certification_expires_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getCertificationExpiredAttribute(): bool
    {
        return $this->certification_expires_at?->isPast() ?? false;
    }
}
