<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TreasuryApprovalLimit extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid', 'company_id', 'created_by', 'updated_by'];

    protected $casts = [
        'minimum_amount' => 'decimal:4', 'maximum_amount' => 'decimal:4',
        'can_create' => 'boolean', 'can_submit' => 'boolean', 'can_approve' => 'boolean',
        'can_post' => 'boolean', 'is_active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date',
    ];
}
