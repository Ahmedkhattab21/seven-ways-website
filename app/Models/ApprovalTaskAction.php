<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApprovalTaskAction extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'approval_task_id', 'actor_id', 'effective_actor_id', 'delegation_id',
        'correlation_id', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];
}
