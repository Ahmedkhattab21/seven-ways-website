<?php

namespace App\Models;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderServiceTimeLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Time logs are append-only.'));
    }

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
