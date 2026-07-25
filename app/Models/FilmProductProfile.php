<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilmProductProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'default_width' => 'decimal:6', 'default_roll_length' => 'decimal:6', 'low_roll_threshold' => 'decimal:6',
        'visible_light_transmission' => 'decimal:2', 'infrared_rejection' => 'decimal:2',
        'uv_rejection' => 'decimal:2', 'heat_rejection' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
