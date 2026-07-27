<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'company_id', 'scope_key', 'is_system'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(function (self $bank) {
            if ($bank->accounts()->exists()) {
                throw new BusinessRuleException('A used bank cannot be deleted.');
            }
        });
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
