<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCommissionSettlementLine extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:4'];

    public function accrual(): BelongsTo
    {
        return $this->belongsTo(EmployeeCommissionAccrual::class, 'commission_accrual_id');
    }
}
