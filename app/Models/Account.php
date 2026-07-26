<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_type_id', 'account_group_id', 'parent_account_id', 'account_code', 'name_ar', 'name_en',
        'description', 'is_header', 'is_posting', 'currency_id', 'allows_multi_currency',
        'requires_cost_center', 'requires_branch', 'requires_customer', 'requires_supplier',
        'requires_employee', 'requires_vehicle', 'is_control_account', 'control_type',
        'is_bank_account', 'is_cash_account', 'is_inventory_account', 'is_tax_account',
        'is_active', 'allow_manual_entry',
    ];

    protected $casts = [
        'is_header' => 'boolean', 'is_posting' => 'boolean', 'allows_multi_currency' => 'boolean',
        'requires_cost_center' => 'boolean', 'requires_branch' => 'boolean', 'requires_customer' => 'boolean',
        'requires_supplier' => 'boolean', 'requires_employee' => 'boolean', 'requires_vehicle' => 'boolean',
        'is_control_account' => 'boolean', 'is_bank_account' => 'boolean', 'is_cash_account' => 'boolean',
        'is_inventory_account' => 'boolean', 'is_tax_account' => 'boolean', 'is_system' => 'boolean',
        'is_active' => 'boolean', 'allow_manual_entry' => 'boolean', 'account_level' => 'integer',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }
}
