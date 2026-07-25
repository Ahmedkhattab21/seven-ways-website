<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'requires_reference', 'is_cash', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'requires_reference' => 'boolean', 'is_cash' => 'boolean',
        'is_active' => 'boolean', 'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointmentDeposits(): HasMany
    {
        return $this->hasMany(AppointmentDeposit::class);
    }
}
