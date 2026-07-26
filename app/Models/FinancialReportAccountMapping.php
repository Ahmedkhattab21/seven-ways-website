<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialReportAccountMapping extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'account_group_id', 'account_type_id', 'sign_multiplier'];

    protected $casts = ['sign_multiplier' => 'decimal:4'];
}
