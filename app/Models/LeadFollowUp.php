<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadFollowUp extends Model
{
    use SoftDeletes;

    protected $fillable = ['follow_up_type', 'scheduled_at', 'completed_at', 'outcome', 'notes', 'next_follow_up_at'];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'next_follow_up_at' => 'datetime'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
