<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingClosingException extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:4', 'waived_at' => 'datetime', 'resolved_at' => 'datetime'];
}
