<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReworkOrderService extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function reworkOrder(): BelongsTo
    {
        return $this->belongsTo(ReworkOrder::class);
    }

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }
}
