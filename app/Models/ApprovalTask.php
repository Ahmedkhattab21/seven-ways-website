<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalTask extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'branch_id', 'approvable_type', 'approvable_id', 'status',
        'requested_by', 'requested_at', 'completed_by', 'completed_at', 'decision', 'delegation_id',
        'idempotency_key',
    ];

    protected $casts = [
        'requested_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalTaskAction::class)->orderBy('occurred_at');
    }
}
