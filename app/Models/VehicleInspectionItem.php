<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleInspectionItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_existing_damage' => 'boolean'];
}
