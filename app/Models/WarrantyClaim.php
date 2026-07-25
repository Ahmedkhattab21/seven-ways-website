<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WarrantyClaim extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'claim_number', 'status', 'decision',
        'is_free', 'customer_charge_amount', 'estimated_company_cost', 'actual_company_cost',
        'approved_by', 'decision_at', 'created_by',
    ];

    protected $casts = [
        'reported_at' => 'datetime', 'inspection_scheduled_at' => 'datetime',
        'inspected_at' => 'datetime', 'decision_at' => 'datetime', 'is_free' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Warranty claims cannot be deleted.'));
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarrantyClaimItem::class);
    }

    public function reworkOrders(): HasMany
    {
        return $this->hasMany(ReworkOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
