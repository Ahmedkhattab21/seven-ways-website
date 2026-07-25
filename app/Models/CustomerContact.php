<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContact extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'job_title', 'phone', 'normalized_phone', 'email', 'is_primary', 'is_active', 'notes'];

    protected $casts = ['is_primary' => 'boolean', 'is_active' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
