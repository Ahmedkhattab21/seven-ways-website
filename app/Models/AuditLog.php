<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['event', 'metadata'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
