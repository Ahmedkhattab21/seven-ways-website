<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'warranty_number', 'status', 'qr_token',
        'issued_at', 'issued_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'terms_snapshot' => 'array',
        'issued_at' => 'datetime', 'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Issued warranties cannot be deleted.'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarrantyItem::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }
}
