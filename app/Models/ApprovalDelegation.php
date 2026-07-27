<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'status', 'created_by', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'modules' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->starts_at->lte(now()) && $this->ends_at->gte(now());
    }
}
