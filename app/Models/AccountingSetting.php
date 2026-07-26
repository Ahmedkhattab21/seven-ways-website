<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSetting extends Model
{
    protected $fillable = [
        'base_currency_id', 'current_fiscal_year_id', 'default_rounding_precision',
        'allow_manual_journals', 'require_journal_approval', 'enforce_balanced_dimensions',
        'enforce_cost_center_on_expense', 'enforce_branch_on_posting',
        'allow_posting_to_soft_closed_period', 'separation_of_duties',
    ];

    protected $casts = [
        'allow_manual_journals' => 'boolean', 'require_journal_approval' => 'boolean',
        'enforce_balanced_dimensions' => 'boolean', 'enforce_cost_center_on_expense' => 'boolean',
        'enforce_branch_on_posting' => 'boolean', 'allow_posting_to_soft_closed_period' => 'boolean',
        'separation_of_duties' => 'boolean', 'auto_post_sales' => 'boolean',
        'auto_post_purchases' => 'boolean', 'auto_post_inventory' => 'boolean',
        'auto_post_payments' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
