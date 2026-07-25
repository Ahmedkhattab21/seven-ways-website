<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchService extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_available', 'booking_enabled', 'requires_approval', 'minimum_notice_minutes',
        'maximum_daily_capacity', 'default_duration_minutes', 'default_price', 'minimum_price',
        'maximum_discount_percentage', 'is_active',
    ];

    protected $casts = [
        'is_available' => 'boolean', 'booking_enabled' => 'boolean', 'requires_approval' => 'boolean',
        'default_price' => 'decimal:4', 'minimum_price' => 'decimal:4',
        'maximum_discount_percentage' => 'decimal:4', 'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
