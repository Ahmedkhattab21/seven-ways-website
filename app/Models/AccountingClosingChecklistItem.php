<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingClosingChecklistItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['is_required' => 'boolean', 'is_automated' => 'boolean', 'evidence' => 'array', 'checked_at' => 'datetime'];
}
