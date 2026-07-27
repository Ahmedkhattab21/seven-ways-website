<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankMatchingRule extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'company_id', 'created_by', 'updated_by'];

    protected $casts = [
        'amount_min' => 'decimal:4', 'amount_max' => 'decimal:4',
        'auto_match' => 'boolean', 'is_active' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
