<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearEndClosingSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'close_revenue_directly_to_retained_earnings' => 'boolean', 'create_opening_journal' => 'boolean',
        'auto_create_next_fiscal_year' => 'boolean', 'auto_generate_next_periods' => 'boolean',
        'lock_year_after_close' => 'boolean', 'require_all_reconciliations' => 'boolean',
    ];
}
