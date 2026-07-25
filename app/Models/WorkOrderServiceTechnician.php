<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderServiceTechnician extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_primary' => 'boolean', 'assigned_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
