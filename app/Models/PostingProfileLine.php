<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingProfileLine extends Model
{
    protected $fillable = [
        'line_number', 'entry_side', 'account_source', 'fixed_account_id', 'amount_source',
        'description_template', 'requires_customer', 'requires_supplier', 'requires_product',
        'requires_branch', 'requires_cost_center', 'tax_component', 'sort_order',
    ];

    protected $casts = [
        'requires_customer' => 'boolean', 'requires_supplier' => 'boolean',
        'requires_product' => 'boolean', 'requires_branch' => 'boolean',
        'requires_cost_center' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PostingProfile::class, 'posting_profile_id');
    }

    public function fixedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fixed_account_id');
    }
}
