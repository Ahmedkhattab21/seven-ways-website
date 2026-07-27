<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCommissionRule extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'company_id', 'created_by', 'updated_by'];

    protected $casts = [
        'rule_value' => 'decimal:4', 'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4', 'effective_from' => 'date',
        'effective_to' => 'date', 'priority' => 'integer', 'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
