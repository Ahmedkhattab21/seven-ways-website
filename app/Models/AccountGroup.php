<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGroup extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['account_type_id', 'parent_group_id', 'code', 'name_ar', 'name_en', 'description', 'sort_order'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean', 'level' => 'integer'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_group_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_group_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
