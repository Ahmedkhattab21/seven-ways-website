<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashFlowMapping extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'account_id', 'account_group_id', 'cash_flow_category', 'cash_flow_line', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
