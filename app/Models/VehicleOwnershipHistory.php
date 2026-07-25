<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleOwnershipHistory extends Model
{
    public $timestamps = false;

    protected $table = 'vehicle_ownership_history';

    protected $fillable = ['transferred_at', 'reason', 'notes'];

    protected $casts = ['transferred_at' => 'datetime', 'created_at' => 'datetime'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }
}
