<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceSettlement extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid', 'company_id', 'status', 'created_by', 'journal_entry_id'];

    protected $casts = ['settlement_date' => 'date', 'amount' => 'decimal:4'];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
