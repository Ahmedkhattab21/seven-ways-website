<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostingProfile extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'source_type', 'description', 'effective_from', 'effective_to', 'is_default',
    ];

    protected $casts = [
        'version' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
        'is_default' => 'boolean', 'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PostingProfileLine::class)->orderBy('sort_order')->orderBy('line_number');
    }
}
