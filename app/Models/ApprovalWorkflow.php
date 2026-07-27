<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid'];

    protected $casts = ['is_active' => 'boolean', 'active_from' => 'datetime', 'active_until' => 'datetime'];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('step_order');
    }
}
