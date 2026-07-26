<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'code', 'name_ar', 'name_en', 'classification', 'normal_balance',
        'statement_type', 'cash_flow_category', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    public function groups(): HasMany
    {
        return $this->hasMany(AccountGroup::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
