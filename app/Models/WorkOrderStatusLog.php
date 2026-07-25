<?php

namespace App\Models;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Model;

class WorkOrderStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('Status logs are append-only.'));
        static::deleting(fn () => throw new BusinessRuleException('Status logs are append-only.'));
    }
}
