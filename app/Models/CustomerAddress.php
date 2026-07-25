<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'label', 'address_type', 'country_code', 'city', 'district', 'street',
        'building_number', 'postal_code', 'additional_number', 'short_address',
        'address_line', 'latitude', 'longitude', 'is_default', 'is_active',
    ];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
