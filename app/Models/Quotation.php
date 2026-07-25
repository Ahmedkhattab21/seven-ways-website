<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'customer_id', 'vehicle_id', 'quotation_date', 'valid_until', 'currency_id',
        'price_includes_tax', 'discount_type', 'discount_value', 'customer_notes', 'internal_notes',
        'terms_and_conditions',
    ];

    protected $casts = [
        'quotation_date' => 'date', 'valid_until' => 'date', 'price_includes_tax' => 'boolean',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'sent_at' => 'datetime',
        'accepted_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quotation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
