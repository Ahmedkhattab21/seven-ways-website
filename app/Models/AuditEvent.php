<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditEvent extends BaseModel
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id', 'uuid', 'company_id', 'branch_id', 'user_id', 'effective_actor_id', 'delegated_by', 'occurred_at', 'created_at'];

    protected $casts = [
        'old_values' => 'array', 'new_values' => 'array', 'changed_fields' => 'array',
        'occurred_at' => 'datetime', 'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
