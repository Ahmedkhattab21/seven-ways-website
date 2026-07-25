<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentDeposit extends BaseModel
{
    use HasFactory;

    protected $fillable = ['amount', 'payment_method_id', 'reference_number', 'received_at', 'notes'];

    protected $casts = ['received_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
