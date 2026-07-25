<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'quotation_id', 'lead_id', 'customer_id', 'vehicle_id', 'scheduled_start', 'scheduled_end',
        'assigned_employee_id', 'booking_source', 'priority', 'deposit_required', 'deposit_amount',
        'customer_notes', 'internal_notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'deposit_required' => 'boolean',
        'checked_in_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class)->orderBy('sort_order');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AppointmentDeposit::class);
    }
}
