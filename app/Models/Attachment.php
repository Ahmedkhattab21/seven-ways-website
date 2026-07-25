<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends BaseModel
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = ['category', 'original_name', 'stored_name', 'disk', 'path', 'mime_type', 'size_bytes'];

    protected $casts = ['created_at' => 'datetime', 'size_bytes' => 'integer'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
