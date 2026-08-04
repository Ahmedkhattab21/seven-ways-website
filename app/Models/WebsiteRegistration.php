<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'country',
        'city',
        'vehicle_type',
        'vehicle_model',
        'vehicle_year',
        'service',
        'preferred_branch',
        'notes',
        'locale',
        'email_sent_at',
    ];

    protected $casts = [
        'vehicle_year' => 'integer',
        'email_sent_at' => 'datetime',
    ];
}
