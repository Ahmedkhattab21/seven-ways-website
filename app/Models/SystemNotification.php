<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemNotification extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'branch_id', 'user_id', 'related_type', 'related_id',
        'idempotency_key', 'read_at', 'dismissed_at',
    ];

    protected $casts = ['metadata' => 'array', 'read_at' => 'datetime', 'dismissed_at' => 'datetime'];

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
