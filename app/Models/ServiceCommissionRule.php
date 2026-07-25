<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCommissionRule extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'employee_id', 'role_id', 'commission_type', 'commission_value',
        'calculation_base', 'minimum_amount', 'maximum_amount', 'effective_from',
        'effective_to', 'priority', 'is_active',
    ];

    protected $casts = [
        'commission_value' => 'decimal:4', 'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date',
        'priority' => 'integer', 'is_active' => 'boolean',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
